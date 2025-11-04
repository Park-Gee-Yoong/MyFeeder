<?php

namespace ParkGeeYoong\MyFeeder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NeoFeederService
{
    protected string $url;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->url = (string) config('neofeeder.url', '');
        $this->username = (string) config('neofeeder.username', '');
        $this->password = (string) config('neofeeder.password', '');
        $this->timeout = (int) config('neofeeder.timeout', 15);
    }

    /**
     * Standardized success wrapper
     */
    protected function successResponse(mixed $data): array
    {
        return [
            'error_code' => 0,
            'error_desc' => '',
            'data' => $data,
        ];
    }

    /**
     * Standardized error wrapper
     */
    protected function errorResponse(int $code, string $message): array
    {
        return [
            'error_code' => $code,
            'error_desc' => $message,
            'data' => null,
        ];
    }

    /**
     * Internal: perform POST and return decoded json or throw (internal)
     * Returns array|null (decoded json) or null on failure.
     */
    protected function postJson(array $payload): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->url, $payload);
        } catch (\Throwable $e) {
            Log::error('NeoFeeder HTTP error: ' . $e->getMessage(), ['exception' => $e, 'payload' => $payload]);
            return null;
        }

        if (! $response->successful()) {
            Log::error('NeoFeeder non-success response', ['status' => $response->status(), 'body' => $response->body(), 'payload' => $payload]);
            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::error('NeoFeeder invalid json', ['body' => $response->body(), 'payload' => $payload]);
            return null;
        }

        return $json;
    }

    /**
     * Ambil token dari NeoFeeder — mengembalikan struktur standar.
     */
    public function getToken(): array
    {
        $payload = [
            'act' => 'GetToken',
            'username' => $this->username,
            'password' => $this->password,
        ];

        $json = $this->postJson($payload);

        if ($json === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // Jika struktur JSON mengandung token di ['data']['token']
        $token = $json['data']['token'] ?? null;

        if (empty($token)) {
            // jika response valid tapi token kosong -> anggap data kosong
            return $this->errorResponse(204, 'data kosong dari neofeeder');
        }

        // kembalikan token di dalam struktur standar (data: original data)
        return $this->successResponse($json['data']);
    }

    /**
     * Memanggil Web Service NeoFeeder (runWS) — mengembalikan struktur standar.
     *
     * @param string $act
     * @param string $filter
     * @param mixed $limit
     * @param mixed $offset
     * @param string $order
     * @return array
     */
    public function runWS(string $act, string $filter = '', $limit = '', $offset = '', string $order = ''): array
    {
        // pertama ambil token (struktur standar)
        $tokenResp = $this->getToken();

        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            // kembalikan error/token-failed langsung
            return $tokenResp;
        }

        // token tersedia pada tokenResp['data']['token']? (ingat getToken mengembalikan data bagian 'data')
        $token = $tokenResp['data']['token'] ?? null;

        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'filter' => $filter,
            'limit' => $limit,
            'offset' => $offset,
            'order' => $order,
        ];

        $json = $this->postJson($payload);

        if ($json === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // jika struktur sukses namun data kosong/null
        if (empty($json['data'])) {
            return $this->errorResponse(204, 'data kosong dari neofeeder');
        }

        // sukses -> bungkus data asli (json['data']) jadi standar
        return $this->successResponse($json['data']);
    }

        /**
     * Insert record ke NeoFeeder.
     *
     * @param string $act   nama action/fitur mis: 'InsertMhs'
     * @param array  $record array data yang akan dikirim sebagai 'record'
     * @return array struktur standar ['error_code' => ..., 'error_desc' => ..., 'data' => ...]
     */
    public function insertWs(string $act, array $record): array
    {
        $tokenResp = $this->getToken();
        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            return $tokenResp;
        }

        $token = $tokenResp['data']['token'] ?? null;
        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'record' => $record,
        ];

        $resp = $this->postJson($payload);
        if ($resp === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // Jika API sudah mengembalikan struktur error_code, kembalikan langsung
        if (isset($resp['error_code'])) {
            return $resp;
        }

        // Normalisasi: bungkus data jika ada, atau kembalikan seluruh respons sebagai data
        return $this->successResponse($resp['data'] ?? $resp);
    }

    /**
     * Update record ke NeoFeeder.
     *
     * @param string $act   nama action/fitur mis: 'UpdateMhs'
     * @param string $key   key/identifier (bisa string atau numeric tergantung API)
     * @param array  $record data update
     * @return array struktur standar
     */
    public function updateWs(string $act, string $key, array $record): array
    {
        $tokenResp = $this->getToken();
        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            return $tokenResp;
        }

        $token = $tokenResp['data']['token'] ?? null;
        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'key' => $key,
            'record' => $record,
        ];

        $resp = $this->postJson($payload);
        if ($resp === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        if (isset($resp['error_code'])) {
            return $resp;
        }

        return $this->successResponse($resp['data'] ?? $resp);
    }

    /**
     * Delete record di NeoFeeder.
     *
     * @param string $act nama action/fitur mis: 'DeleteMhs'
     * @param string $key key/identifier yang akan dihapus
     * @return array struktur standar
     */
    public function deleteWs(string $act, string $key): array
    {
        $tokenResp = $this->getToken();
        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            return $tokenResp;
        }

        $token = $tokenResp['data']['token'] ?? null;
        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'key' => $key,
        ];

        $resp = $this->postJson($payload);
        if ($resp === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        if (isset($resp['error_code'])) {
            return $resp;
        }

        return $this->successResponse($resp['data'] ?? $resp);
    }

    /**
     * Helper detWs: shortcut untuk filter sederhana — mengembalikan struktur standar.
     */
    public function detWs(string $fitur, string $field, string $id): array
    {
        $newId = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $filter = "{$field} = '{$newId}'";
        return $this->runWS($fitur, $filter, '', '', '');
    }

        /**
     * Panggilan khusus 'diktio' — mengirim parameter 'fungsi' sesuai versi lama.
     *
     * @param string $act   nama action/fitur
     * @param mixed  $dtinst nilai fungsi (bisa string atau array sesuai API NeoFeeder)
     * @param string $type  (tidak digunakan — hanya untuk kompatibilitas, default 'json')
     * @return array struktur standar
     */
    public function diktio(string $act, $dtinst, string $type = 'json'): array
    {
        // ambil token dulu
        $tokenResp = $this->getToken();
        if (isset($tokenResp['error_code']) && $tokenResp['error_code'] !== 0) {
            return $tokenResp;
        }

        $token = $tokenResp['data']['token'] ?? null;
        if (empty($token)) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        $payload = [
            'act' => $act,
            'token' => $token,
            'fungsi' => $dtinst,
        ];

        $resp = $this->postJson($payload);
        if ($resp === null) {
            return $this->errorResponse(404, 'terputus dari neofeeder');
        }

        // Jika API sudah mengembalikan struktur error_code, kembalikan langsung
        if (isset($resp['error_code'])) {
            return $resp;
        }

        return $this->successResponse($resp['data'] ?? $resp);
    }

    /**
     * detlimWs — cari berdasarkan field dengan limit (kompatibel dengan versi lama).
     *
     * @param string $fitur nama fitur/act
     * @param string $field nama field untuk filter
     * @param string $id    nilai id (akan disanitasi)
     * @param int|string $lim limit hasil
     * @return array struktur standar
     */
    public function detlimWs(string $fitur, string $field, string $id, $lim): array
    {
        $newId = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $filter = "{$field} = '{$newId}'";
        $limit = $lim;
        $offset = '';
        $order = '';

        return $this->runWS($fitur, $filter, $limit, $offset, $order);
    }

    /**
     * detlimfitur — ambil fitur dengan limit
     *
     * @param string $fitur
     * @param int|string $lim
     * @return array struktur standar
     */
    public function detlimfitur(string $fitur, $lim): array
    {
        $filter = "";
        $limit = $lim;
        $offset = '';
        $order = '';

        return $this->runWS($fitur, $filter, $limit, $offset, $order);
    }

    /**
     * allGet — ambil semua tanpa filter
     *
     * @param string $fitur
     * @return array struktur standar
     */
    public function allGet(string $fitur): array
    {
        return $this->runWS($fitur, '', '', '', '');
    }

    /**
     * allGetOrder — ambil semua dengan order tertentu
     *
     * @param string $fitur
     * @param string $field nama field untuk order
     * @param string $dir   arah order ('ASC' atau 'DESC' biasa)
     * @return array struktur standar
     */
    public function allGetOrder(string $fitur, string $field, string $dir = 'ASC'): array
    {
        $order = trim($field . ' ' . strtoupper($dir));
        return $this->runWS($fitur, '', '', '', $order);
    }

    /**
     * getProfilPT — ambil profil perguruan tinggi
     *
     * @return array struktur standar
     */
    public function getProfilPT(): array
    {
        $fitur = 'GetProfilPT';
        $filter = "";
        return $this->runWS($fitur, $filter, '', '', '');
    }

        /**
     * Ambil semua program studi (GetAllProdi) berdasarkan profil PT yang aktif.
     */
    public function getAllProdi(): array
    {
        $profilResp = $this->getProfilPT();
        if (isset($profilResp['error_code']) && $profilResp['error_code'] !== 0) {
            return $profilResp;
        }

        $profil = $profilResp['data'][0] ?? null;
        if (! is_array($profil) && ! is_object($profil)) {
            return $this->errorResponse(204, 'data profil PT tidak ditemukan');
        }

        // dapatkan id_perguruan_tinggi — dukung array atau objek
        $idPt = is_array($profil) ? ($profil['id_perguruan_tinggi'] ?? null) : ($profil->id_perguruan_tinggi ?? null);
        if (empty($idPt)) {
            return $this->errorResponse(204, 'id_perguruan_tinggi tidak tersedia');
        }

        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $idPt);
        $act = 'GetAllProdi';
        $filter = "id_perguruan_tinggi = '{$newid}'";
        $limit = '';
        $offset = '';
        $order = 'id_jenjang_pendidikan ASC';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Cari wilayah berdasarkan nama (ilike %...%).
     *
     * @param string $id pencarian (fragmen nama)
     */
    public function getIdWilayah(string $id): array
    {
        $safe = addslashes(trim($id));
        $act = 'GetWilayah';
        $filter = "nama_wilayah ilike '%{$safe}%'";
        $limit = '5';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Ambil wilayah berdasarkan id_wilayah exact match.
     *
     * @param string $id id_wilayah
     */
    public function getIdWilayahByID(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetWilayah';
        $filter = "id_wilayah = '{$newid}'";
        $limit = '5';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Hitung jumlah mahasiswa berdasarkan prodi, semester, dan status.
     *
     * @param string $prodi id_prodi
     * @param string $smt   id_semester
     * @param string $sts   id_status_mahasiswa
     */
    public function jmlstsmhs(string $prodi, string $smt, string $sts): array
    {
        $newprodi = preg_replace('/[^a-zA-Z0-9\-]/', '', $prodi);
        $newsmt   = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $newssts  = preg_replace('/[^a-zA-Z0-9\-]/', '', $sts);

        $act = 'GetCountPerkuliahanMahasiswa';
        $filter = "id_prodi = '{$newprodi}' and id_semester = '{$newsmt}' and id_status_mahasiswa = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Hitung jumlah mahasiswa berdasarkan periode dan agama.
     *
     * @param string $periode id_periode
     * @param string $sts     id_agama
     */
    public function jmlmhsbyagama(string $periode, string $sts): array
    {
        $newperiode = preg_replace('/[^a-zA-Z0-9\-]/', '', $periode);
        $newssts    = preg_replace('/[^a-zA-Z0-9\-]/', '', $sts);

        $act = 'GetCountMahasiswa';
        $filter = "id_periode = '{$newperiode}' and id_agama = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

        /**
     * Jumlah mahasiswa berdasarkan periode dan nama status mahasiswa (jenis keluar).
     *
     * @param string $periode id_periode
     * @param string $sts     nama_status_mahasiswa
     * @return array
     */
    public function jmlmhsbyjnskeluar(string $periode, string $sts): array
    {
        $newperiode = preg_replace('/[^a-zA-Z0-9\-]/', '', $periode);
        $newssts    = preg_replace('/[^a-zA-Z0-9\-]/', '', $sts);

        $act = 'GetCountMahasiswa';
        $filter = "id_periode = '{$newperiode}' and nama_status_mahasiswa = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Jumlah mahasiswa berdasarkan jenis kelamin (dengan status 'AKTIF').
     *
     * @param string $jenisKelamin contoh: 'L' atau 'P' atau teks lain sesuai NeoFeeder
     * @return array
     */
    public function jmlmhsbylp(string $jenisKelamin): array
    {
        $newssts = preg_replace('/[^a-zA-Z0-9\-]/', '', $jenisKelamin);

        $act = 'GetCountMahasiswa';
        $filter = "nama_status_mahasiswa = 'AKTIF' and jenis_kelamin = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Jumlah mahasiswa dengan status tertentu (mis. 'AKTIF').
     *
     * @param string $sts nama_status_mahasiswa
     * @return array
     */
    public function jmlmhsaktif(string $sts): array
    {
        $newssts = preg_replace('/[^a-zA-Z0-9\-]/', '', $sts);

        $act = 'GetCountMahasiswa';
        $filter = "nama_status_mahasiswa = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Jumlah mahasiswa aktif berdasarkan periode.
     *
     * @param string $periode id_periode
     * @param string $sts     nama_status_mahasiswa
     * @return array
     */
    public function jmlmhsaktifbyperiode(string $periode, string $sts): array
    {
        $newperiode = preg_replace('/[^a-zA-Z0-9\-]/', '', $periode);
        $newssts    = preg_replace('/[^a-zA-Z0-9\-]/', '', $sts);

        $act = 'GetCountMahasiswa';
        $filter = "id_periode = '{$newperiode}' and nama_status_mahasiswa = '{$newssts}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Hitung penugasan dosen per prodi untuk tahun ajaran tertentu.
     *
     * @param string $tahun id_tahun_ajaran
     * @param string $id    id_prodi
     * @return array
     */
    public function getPenugasanDsnProdi(string $tahun, string $id): array
    {
        $newtahun = preg_replace('/[^a-zA-Z0-9\-]/', '', $tahun);
        $newid    = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);

        $act = 'GetCountPenugasanSemuaDosen';
        $filter = "id_tahun_ajaran = '{$newtahun}' and id_prodi = '{$newid}'";
        $limit = '';
        $offset = '';
        $order = '';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Detail perkuliahan mahasiswa berdasarkan id registrasi mahasiswa.
     *
     * @param string $idreg id_registrasi_mahasiswa
     * @return array
     */
    public function getKuliahMhs(string $idreg): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);

        $act = 'GetDetailPerkuliahanMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        $limit = '';
        $offset = '';
        $order = 'id_semester desc';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

    /**
     * Ambil semua KRS mahasiswa berdasarkan id registrasi mahasiswa.
     *
     * @param string $idreg id_registrasi_mahasiswa
     * @return array
     */
    public function getAllKrsMhs(string $idreg): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);

        $act = 'GetKRSMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        $limit = '';
        $offset = '';
        $order = 'id_periode asc';

        return $this->runWS($act, $filter, $limit, $offset, $order);
    }

        /**
     * GetNilaiTransferPendidikanMahasiswa
     */
    public function GetNilaiTransferPendidikanMahasiswa(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetNilaiTransferPendidikanMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetNilaiTfByProdAng
     */
    public function GetNilaiTfByProdAng(string $id, string $smt): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetNilaiTransferPendidikanMahasiswa';
        $filter = "id_prodi = '{$newid}' and id_periode_masuk = '{$newsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetListKonversiKampusMerdeka
     */
    public function GetListKonversiKampusMerdeka(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetListKonversiKampusMerdeka';
        $filter = "nim = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getRiwayatPendidikanMhs
     */
    public function getRiwayatPendidikanMhs(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetListRiwayatPendidikanMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getRiwayatnMhs (by id_mahasiswa)
     */
    public function getRiwayatnMhs(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetListRiwayatPendidikanMahasiswa';
        $filter = "id_mahasiswa = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getTraskripMhs
     */
    public function getTraskripMhs(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetTranskripMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getNilaiTraskrip (per matkul)
     */
    public function getNilaiTraskrip(string $id, string $matkul): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newmatkul = preg_replace('/[^a-zA-Z0-9\-]/', '', $matkul);
        $act = 'GetTranskripMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newid}' and id_matkul = '{$newmatkul}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * NilaiByKlsNim
     */
    public function NilaiByKlsNim(string $id, string $nim): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newnim = preg_replace('/[^a-zA-Z0-9\-]/', '', $nim);
        $act = 'GetDetailNilaiPerkuliahanKelas';
        $filter = "id_kelas_kuliah = '{$newid}' and nim = '{$newnim}'";
        return $this->runWS($act, $filter, '', '', 'nim asc');
    }

    /**
     * NilaiByKlsIdreg
     */
    public function NilaiByKlsIdreg(string $id, string $nim): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newnim = preg_replace('/[^a-zA-Z0-9\-]/', '', $nim);
        $act = 'GetDetailNilaiPerkuliahanKelas';
        $filter = "id_kelas_kuliah = '{$newid}' and id_registrasi_mahasiswa = '{$newnim}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getNilaiMhs
     */
    public function getNilaiMhs(string $idreg): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $act = 'GetRiwayatNilaiMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        return $this->runWS($act, $filter, '', '', 'id_periode');
    }

    /**
     * getKrsMhsBySmt
     */
    public function getKrsMhsBySmt(string $idreg, string $idsmt): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $newidsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsmt);
        $act = 'GetKRSMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}' and id_periode = '{$newidsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getAkMhsByReg (desc)
     */
    public function getAkMhsByReg(string $id): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetAktivitasKuliahMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        return $this->runWS($act, $filter, '', '', 'id_semester desc');
    }

    /**
     * getAkMhsByRegAsc
     */
    public function getAkMhsByRegAsc(string $id): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetAktivitasKuliahMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        return $this->runWS($act, $filter, '', '', 'id_semester asc');
    }

    /**
     * getAkMAktifhsByReg (status A)
     */
    public function getAkMAktifhsByReg(string $id): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetAktivitasKuliahMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}' and id_status_mahasiswa = 'A'";
        return $this->runWS($act, $filter, '', '', 'id_semester desc');
    }

    /**
     * getProfilPTByName
     */
    public function getProfilPTByName(string $id): array
    {
        $safe = addslashes(trim($id));
        $act = 'GetAllPT';
        $filter = "nama_perguruan_tinggi like '%{$safe}%'";
        return $this->runWS($act, $filter, '10', '', '');
    }

    /**
     * getmkakuibynm
     */
    public function getmkakuibynm(string $idsms, string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newsms = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsms);
        $act = 'GetMatkulKurikulum';
        $filter = "id_prodi = '{$newsms}' and nama_mata_kuliah like '%{$newid}%'";
        return $this->runWS($act, $filter, '10', '', 'id_semester desc');
    }

    /**
     * getKelasKuliah
     */
    public function getKelasKuliah(string $idsms, string $idsmt): array
    {
        $newidsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsmt);
        $newidsms = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsms);
        $act = 'GetDetailKelasKuliah';
        $filter = "id_prodi = '{$newidsms}' and id_semester = '{$newidsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMk (search by name)
     */
    public function getMk(string $id): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $id);
        $safe = preg_replace('/\s+/', ' ', trim($safe));
        $act = 'GetDetailMataKuliah';
        $filter = "nama_mata_kuliah like '%{$safe}%'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getdetKelasKuliah
     */
    public function getdetKelasKuliah(string $idkls): array
    {
        $newidkls = preg_replace('/[^a-zA-Z0-9\-]/', '', $idkls);
        $act = 'GetDetailKelasKuliah';
        $filter = "id_kelas_kuliah = '{$newidkls}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMkKurMhs (last semester)
     */
    public function getMkKurMhs(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetMatkulKurikulum';
        $filter = "id_matkul = '{$newid}'";
        return $this->runWS($act, $filter, '1', '', 'id_semester desc');
    }

    /**
     * getMkKur
     */
    public function getMkKur(string $idmk): array
    {
        $newidsms = preg_replace('/[^a-zA-Z0-9\-]/', '', $idmk);
        $act = 'GetMatkulKurikulum';
        $filter = "id_matkul = '{$newidsms}'";
        return $this->runWS($act, $filter, '', '', 'tgl_create desc');
    }

    /**
     * getMkKurByKur
     */
    public function getMkKurByKur(string $idkur, string $idmk): array
    {
        $newidkur = preg_replace('/[^a-zA-Z0-9\-]/', '', $idkur);
        $newidmk  = preg_replace('/[^a-zA-Z0-9\-]/', '', $idmk);
        $act = 'GetMatkulKurikulum';
        $filter = "id_kurikulum = '{$newidkur}' and id_matkul = '{$newidmk}'";
        return $this->runWS($act, $filter, '', '', 'tgl_create desc');
    }

    /**
     * countKlsProdi
     */
    public function countKlsProdi(string $idsms, string $idsmt): array
    {
        $newidsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsmt);
        $newidsms = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsms);
        $act = 'GetCountKelasKuliahWs';
        $filter = "id_prodi = '{$newidsms}' and id_semester = '{$newidsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMhsByNIM
     */
    public function getMhsByNIM(string $nim): array
    {
        $newnim = preg_replace('/[^0-9]/', '', $nim);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nim = '{$newnim}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMhsByNIMLim
     */
    public function getMhsByNIMLim(string $nim, $lim): array
    {
        $newnim = preg_replace('/[^0-9]/', '', $nim);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nim = '{$newnim}' and nama_status_mahasiswa = 'AKTIF'";
        return $this->runWS($act, $filter, $lim, '', '');
    }

    /**
     * searchMhsByNIMLim
     */
    public function searchMhsByNIMLim(string $nim, $lim): array
    {
        $newnim = preg_replace('/[^0-9]/', '', $nim);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nim like '%{$newnim}%' and nama_status_mahasiswa = 'AKTIF'";
        return $this->runWS($act, $filter, $lim, '', '');
    }

    /**
     * searchMhsByNIMLim2
     */
    public function searchMhsByNIMLim2(string $nim, $lim): array
    {
        $newnim = preg_replace('/[^0-9]/', '', $nim);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nim like '%{$newnim}%'";
        return $this->runWS($act, $filter, $lim, '', '');
    }

    /**
     * getMhsByNama
     */
    public function getMhsByNama(string $nim): array
    {
        $newnim = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $nim);
        $newnim = preg_replace('/\s+/', ' ', trim($newnim));
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nama_mahasiswa like '%{$newnim}%' and nama_status_mahasiswa = 'AKTIF'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMhsByNamaLim
     */
    public function getMhsByNamaLim(string $nim, $lim): array
    {
        $newnim = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $nim);
        $newnim = preg_replace('/\s+/', ' ', trim($newnim));
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nama_mahasiswa like '%{$newnim}%' and nama_status_mahasiswa = 'AKTIF'";
        return $this->runWS($act, $filter, $lim, '', '');
    }

    /**
     * getMhsByIdRegis
     */
    public function getMhsByIdRegis(string $nim): array
    {
        $newnim = preg_replace('/[^a-zA-Z0-9\-]/', '', $nim);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "id_registrasi_mahasiswa = '{$newnim}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getAktivitasKuliahMhs
     */
    public function getAktivitasKuliahMhs(string $idreg): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $act = 'GetAktivitasKuliahMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}'";
        return $this->runWS($act, $filter, '', '', 'id_semester desc');
    }

    /**
     * getAkmBySmt
     */
    public function getAkmBySmt(string $idreg, string $smt): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetAktivitasKuliahMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}' and id_semester = '{$newsmt}'";
        return $this->runWS($act, $filter, '', '', 'id_semester desc');
    }

    /**
     * cekAkmBySmt
     */
    public function cekAkmBySmt(string $idreg, string $smt): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetCountPerkuliahanMahasiswa';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}' and id_semester = '{$newsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getAktivitasByProdSmt
     */
    public function getAktivitasByProdSmt(string $idreg, string $smt): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetListAktivitasMahasiswa';
        $filter = "id_prodi = '{$newidreg}' and id_semester = '{$newsmt}'";
        return $this->runWS($act, $filter, '', '', 'id_semester desc');
    }

    /**
     * getAktivitasByName
     */
    public function getAktivitasByName(string $idreg, $lim): array
    {
        $safe = addslashes(trim($idreg));
        $act = 'GetListAktivitasMahasiswa';
        $filter = "judul like '%{$safe}%'";
        return $this->runWS($act, $filter, $lim, '', 'id_semester desc');
    }

    /**
     * getAnggotaByName
     */
    public function getAnggotaByName(string $aktivitas, string $cari, $lim): array
    {
        $safeCari = addslashes(trim($cari));
        $act = 'GetListAnggotaAktivitasMahasiswa';
        $filter = "id_aktivitas = '{$aktivitas}' and nama_mahasiswa like '%{$safeCari}%'";
        return $this->runWS($act, $filter, $lim, '', '');
    }

    /**
     * getNilaiByidRegAndIdSmt
     */
    public function getNilaiByidRegAndIdSmt(string $idreg, string $idsmt): array
    {
        $newidreg = preg_replace('/[^a-zA-Z0-9\-]/', '', $idreg);
        $newidsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $idsmt);
        $act = 'GetDetailNilaiPerkuliahanKelas';
        $filter = "id_registrasi_mahasiswa = '{$newidreg}' and id_semester = '{$newidsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getDosenByNama
     */
    public function getDosenByNama(string $id): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $id);
        $safe = preg_replace('/\s+/', ' ', trim($safe));
        $act = 'DetailBiodataDosen';
        $filter = "nama_dosen like '%{$safe}%'";
        return $this->runWS($act, $filter, '10', '', '');
    }

    /**
     * getKategoriKegiatan
     */
    public function getKategoriKegiatan(string $id): array
    {
        $safe = addslashes(trim($id));
        $act = 'GetKategoriKegiatan';
        $filter = "nama_kategori_kegiatan like '%{$safe}%'";
        return $this->runWS($act, $filter, '10', '', '');
    }

    /**
     * getDosenByNidn
     */
    public function getDosenByNidn(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'DetailBiodataDosen';
        $filter = "nidn = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getDosenByNidnNuptk
     */
    public function getDosenByNidnNuptk(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'DetailBiodataDosen';
        $filter = "nidn = '{$newid}' or nuptk = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getDosenById
     */
    public function getDosenById(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'DetailBiodataDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getTgsDosenSmt
     */
    public function getTgsDosenSmt(string $id, string $smt): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetListPenugasanDosen';
        $filter = "nidn = '{$newid}' and id_tahun_ajaran = {$newsmt}";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getPenugasanDosen
     */
    public function getPenugasanDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetDetailPenugasanDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getPenugasanDosenSmt
     */
    public function getPenugasanDosenSmt(string $id, string $smt): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetDetailPenugasanDosen';
        $filter = "id_dosen = '{$newid}' and id_tahun_ajaran = {$newsmt}";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetRiwayatPangkatDosen
     */
    public function GetRiwayatPangkatDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetRiwayatPangkatDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetRiwayatPendidikanDosen
     */
    public function GetRiwayatPendidikanDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetRiwayatPendidikanDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetRiwayatFungsionalDosen
     */
    public function GetRiwayatFungsionalDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetRiwayatFungsionalDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetRiwayatSertifikasiDosen
     */
    public function GetRiwayatSertifikasiDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetRiwayatSertifikasiDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetRiwayatPenelitianDosen
     */
    public function GetRiwayatPenelitianDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetRiwayatPenelitianDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * GetMahasiswaBimbinganDosen
     */
    public function GetMahasiswaBimbinganDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetMahasiswaBimbinganDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getKelasMengajarDosen
     */
    public function getKelasMengajarDosen(string $id, string $smt): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $newsmt = preg_replace('/[^a-zA-Z0-9\-]/', '', $smt);
        $act = 'GetDosenPengajarKelasKuliah';
        $filter = "id_dosen = '{$newid}' and id_semester = '{$newsmt}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getDosenKelas
     */
    public function getDosenKelas(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetDosenPengajarKelasKuliah';
        $filter = "id_kelas_kuliah = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getAktivitasMengajarDosen
     */
    public function getAktivitasMengajarDosen(string $id): array
    {
        $newid = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);
        $act = 'GetAktivitasMengajarDosen';
        $filter = "id_dosen = '{$newid}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMhsByProdiAng
     */
    public function getMhsByProdiAng(string $prodi, string $angkatan): array
    {
        $newnimprodi = preg_replace('/[^a-zA-Z0-9\-]/', '', $prodi);
        $newangkatan = preg_replace('/[^a-zA-Z0-9\-]/', '', $angkatan);
        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "id_prodi = '{$newnimprodi}' and id_periode_masuk = '{$newangkatan}'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getBiodataMhs
     */
    public function getBiodataMhs(string $nama, string $ibu): array
    {
        $newnama = str_replace("'", "''", $nama);
        $newibu  = str_replace("'", "''", $ibu);

        $newnama = preg_replace("/[^A-Za-z0-9\s']/", ' ', $newnama);
        $newibu  = preg_replace("/[^A-Za-z0-9\s']/", ' ', $newibu);

        $newnama = preg_replace('/\s+/', ' ', trim($newnama));
        $newibu  = preg_replace('/\s+/', ' ', trim($newibu));

        $act = 'GetDataLengkapMahasiswaProdi';
        $filter = "nama_mahasiswa like '%{$newnama}%' and nama_ibu_kandung like '%{$newibu}%'";
        return $this->runWS($act, $filter, '', '', '');
    }

    /**
     * getMhsLlsDoByProdSmt
     */
    public function getMhsLlsDoByProdSmt(string $prodi, string $angkatan): array
    {
        $newnimprodi = preg_replace('/[^a-zA-Z0-9\-]/', '', $prodi);
        $newangkatan = preg_replace('/[^a-zA-Z0-9\-]/', '', $angkatan);
        $act = 'GetListMahasiswaLulusDO';
        $filter = "id_prodi = '{$newnimprodi}' and id_periode_keluar = '{$newangkatan}'";
        return $this->runWS($act, $filter, '', '', '');
    }

}