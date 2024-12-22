<?php

require_once 'interface.php';
abstract class KaryawanAbstract implements HitungGajiInterface {
    protected $id;
    protected $nama;
    protected $jabatan;
    protected $alamat;
    protected $telepon;
    protected $tempatLahir;
    protected $tanggalLahir;
    protected $gajibulanan;
    protected $gajiLembur;

    public function __construct($id, $nama, $jabatan, $gajiBulanan, $alamat, $telepon, $tempatLahir, $tanggalLahir) {
        $this->id = $id ?: $this->generateRandomId();
        $this->nama = $nama;
        $this->jabatan = $jabatan;
        $this->alamat = $alamat;
        $this->telepon = $telepon;
        $this->tempatLahir = $tempatLahir;
        $this->tanggalLahir = $tanggalLahir;
        $this->gajibulanan = $gajiBulanan;
        $this->gajiLembur = 0;
    }

    private function generateRandomId() {
        return rand(1000, 9999);
    }

    public static function validateNamaKaryawan($pdo, $nama) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Data_karyawan WHERE nama = :nama");
        $stmt->execute(['nama' => $nama]);
        return $stmt->fetchColumn() > 0;
    }

    public static function validateTanggalLahir($tanggalLahir) {
        $d = DateTime::createFromFormat('Y-m-d', $tanggalLahir);
        return $d && $d->format('Y-m-d') === $tanggalLahir;
    }

    public function getAlamat() {
        return $this->alamat;
    }

    public function getTelepon() {
        return $this->telepon;
    }

    public function getTempatLahir() {
        return $this->tempatLahir;
    }

    public function getTanggalLahir() {
        return $this->tanggalLahir;
    }

    public function setAlamat($alamat) {
        $this->alamat = $alamat;
    }

    public function setTelepon($telepon) {
        $this->telepon = $telepon;
    }

    public function tampilkanInfo(): string {
        return str_pad($this->id, 10) . 
               str_pad($this->nama, 20) . 
               str_pad($this->alamat, 25) .  
               str_pad($this->telepon, 15) . 
               str_pad($this->tempatLahir, 15) . 
               str_pad($this->tanggalLahir, 15) . 
               str_pad($this->jabatan, 15);
    }

    public function getId() {
        return $this->id;
    }

    public function getNama() {
        return $this->nama;
    }

    public function getJabatan() {
        return $this->jabatan;
    }

    public function getGaji() {
        return $this->gajibulanan;
    }

    public function getLembur() {
        return $this->gajiLembur;
    }

    public function setNama($nama) {
        $this->nama = $nama;
    }

    public function setJabatan($jabatan) {
        $this->jabatan = $jabatan;
    }

    public function setGaji($gaji) {
        $this->gajibulanan = $gaji;
    }

    public function setLembur($lembur) {
        $this->gajiLembur = $lembur;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function validTanggalLahir($tanggalLahir) {
        $this->tanggalLahir = $tanggalLahir;
    }
}
