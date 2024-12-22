<?php

require_once 'database.php';
require_once 'karyawanabstract.php';

class Karyawan extends KaryawanAbstract {
    public function hitungGaji(): float {
        return $this->gajibulanan + $this->gajiLembur;
    }

    public function tambahGajiLembur($pdo, $jamLembur) {
        if ($jamLembur < 0) {
            echo "Jumlah jam lembur tidak boleh negatif.\n";
            return;
        }

        $lemburPerJam = 50000;
        $gajiLembur = $jamLembur * $lemburPerJam;

        try {
            $stmt = $pdo->prepare("UPDATE Gaji_karyawan SET gaji_lembur = :gaji_lembur WHERE id = :id");
            $stmt->execute([
                'gaji_lembur' => $gajiLembur,
                'id' => $this->id
            ]);

            $stmt = $pdo->prepare("SELECT gaji_bulanan FROM Gaji_karyawan WHERE id = :id");
            $stmt->execute(['id' => $this->id]);
            $row = $stmt->fetch();

            if ($row) {
                $gajiBulanan = $row['gaji_bulanan'];
                $totalGaji = $gajiBulanan + $gajiLembur;

                $stmt = $pdo->prepare("UPDATE Gaji_karyawan SET total_gaji = :total_gaji WHERE id = :id");
                $stmt->execute([
                    'total_gaji' => $totalGaji,
                    'id' => $this->id
                ]);

                $this->setLembur($gajiLembur);

                echo "Gaji lembur untuk karyawan dengan ID {$this->id} berhasil diperbarui menjadi Rp" . number_format($gajiLembur, 0, ',', '.') . ".\n";
                echo "Total gaji diperbarui menjadi Rp" . number_format($totalGaji, 0, ',', '.') . ".\n";
            } else {
                echo "Karyawan dengan ID {$this->id} tidak ditemukan.\n";
            }
        } catch (PDOException $e) {
            echo "Query gagal: " . $e->getMessage() . "\n";
        }
    }

    public function prosesPembayaranGaji($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT dk.id AS id, gk.total_gaji 
                                    FROM Data_karyawan dk 
                                    JOIN Gaji_karyawan gk ON dk.id = gk.id
                                    WHERE dk.id = :id");
            $stmt->execute(['id' => $this->id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
            if ($result) {
                // Use null coalescing operator to avoid undefined array key warnings
                $totalGaji = $result['total_gaji'] ?? 0; // Default to 0 if not set
                $karyawanId = $result['id'] ?? null; // Default to null if not set
                $tanggalPembayaran = date('Y-m-d');
  
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Pembayaran_gaji WHERE id = :id AND tanggal_pembayaran = :tanggal_pembayaran");
                $stmtCheck->execute(['id' => $karyawanId, 'tanggal_pembayaran' => $tanggalPembayaran]);
                $exists = $stmtCheck->fetchColumn();
    
                if ($exists > 0) {
                    echo "Pembayaran gaji untuk karyawan dengan ID {$karyawanId} sudah diproses pada tanggal $tanggalPembayaran.\n";
                    return;
                }
    
                $stmt = $pdo->prepare("INSERT INTO Pembayaran_gaji (id, tanggal_pembayaran, total_gaji) VALUES (:id, :tanggal_pembayaran, :total_gaji)");
                $stmt->execute([
                    'id' => $karyawanId,
                    'tanggal_pembayaran' => $tanggalPembayaran,
                    'total_gaji' => $totalGaji
                ]);
        
                echo "Pembayaran gaji untuk karyawan dengan ID {$karyawanId} sebesar Rp" . number_format($totalGaji, 0, ',', '.') . " berhasil diproses pada tanggal $tanggalPembayaran.\n";
            } else {
                echo "Data karyawan dengan ID {$this->id} tidak ditemukan.\n";
            }
        } catch (PDOException $e) {
            echo "Proses pembayaran gaji gagal: " . $e->getMessage() . "\n";
        }
    }

    public function setLembur($gajiLembur) {
        $this->gajiLembur = $gajiLembur;
    }
}

class Sistem {
    private $karyawan = [];
    public $pdo;

    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }

    public function muatKaryawanDariDatabase() {
        try {
            $stmt = $this->pdo->query("SELECT dk.id, dk.nama, dk.jabatan, dk.alamat, dk.telepon, dk.tempat_lahir, dk.tanggal_lahir, gk.gaji_bulanan, gk.gaji_lembur
                                        FROM Data_karyawan dk 
                                        JOIN Gaji_karyawan gk ON dk.id = gk.id");

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                echo "\nTidak ada data karyawan untuk ditampilkan.\n";
                return;
            }

            echo "\n=== Data Karyawan ===\n";
            echo str_pad("ID", 10) . str_pad("Nama", 20) . str_pad("Jabatan", 15) . str_pad("Alamat", 15) . str_pad("Telepon", 15) . str_pad("Tempat_Lahir", 15) . str_pad("Tanggal_Lahir", 15) . "\n";
            echo str_repeat("=", 105) . "\n";

            foreach ($result as $karyawan) {
                echo str_pad($karyawan['id'], 10) . 
                    str_pad($karyawan['nama'] ?? 'Tidak Diketahui', 20) . 
                    str_pad($karyawan['jabatan'], 15) . 
                    str_pad($karyawan['alamat'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($karyawan['telepon'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($karyawan['tempat_lahir'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($karyawan['tanggal_lahir'] ?? 'Tidak Diketahui', 15) . "\n";
            }

            echo str_repeat("=", 105) . "\n";
            echo "Selesai menampilkan data karyawan.\n";

        } catch (PDOException $e) {
            echo "Query gagal: " . $e->getMessage() . "\n";
            exit;
        }
    }

    public function tambahKaryawan(KaryawanAbstract $karyawanBaru) {
        try {
            $id = $karyawanBaru->getId();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Data_karyawan WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                echo "ID karyawan sudah ada di database. Gunakan ID yang lain.\n";
                return;
            }

            $stmt = $this->pdo->prepare("INSERT INTO Data_karyawan (id, nama, jabatan, alamat, telepon, tempat_lahir, tanggal_lahir) 
                                        VALUES (:id, :nama, :jabatan, :alamat, :telepon, :tempat_lahir, :tanggal_lahir)");
            $stmt->execute([
                'id' => $id,
                'nama' => $karyawanBaru->getNama(),
                'jabatan' => $karyawanBaru->getJabatan(),
                'alamat' => $karyawanBaru->getAlamat(),
                'telepon' => $karyawanBaru->getTelepon(),
                'tempat_lahir' => $karyawanBaru->getTempatLahir(),
                'tanggal_lahir' => $karyawanBaru->getTanggalLahir(),
            ]);

            $stmtGaji = $this->pdo->prepare("INSERT INTO Gaji_karyawan (id, nama, jabatan, telepon, gaji_bulanan, gaji_lembur, total_gaji) 
                                            VALUES (:id, :nama, :jabatan, :telepon, :gaji_bulanan, :gaji_lembur, :total_gaji)");
            $stmtGaji->execute([
                'id' => $id,
                'nama' => $karyawanBaru->getNama(),
                'jabatan' => $karyawanBaru->getJabatan(),
                'telepon' => $karyawanBaru->getTelepon(),
                'gaji_bulanan' => $karyawanBaru->getGaji(),
                'gaji_lembur' => 0,
                'total_gaji' => $karyawanBaru->getGaji(),
            ]);

            echo "\nKaryawan baru dengan ID $id berhasil ditambahkan!\n";
        } catch ( PDOException $e) {
            echo "Query gagal: " . $e->getMessage() . "\n";
            exit;
        }
    }

    public function cariKaryawanBerdasarkanId($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT dk.id, dk.nama, dk.jabatan, dk.alamat, dk.telepon, dk.tempat_lahir, dk.tanggal_lahir, gk.gaji_bulanan, gk.gaji_lembur, gk.total_gaji
                                        FROM Data_karyawan dk
                                        JOIN Gaji_karyawan gk ON dk.id = gk.id
                                        WHERE dk.id = :id");
            $stmt->execute([':id' => $id]);
    
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($row) {
                $karyawan = new Karyawan(
                    $row['id'],
                    $row['nama'] ?? 'Tidak Diketahui', 
                    $row['jabatan'] ?? 'Tidak Diketahui', 
                    $row['gaji_bulanan'] ?? 0, 
                    $row['alamat'] ?? 'Tidak Diketahui', 
                    $row['telepon'] ?? 'Tidak Diketahui', 
                    $row['tempat_lahir'] ?? 'Tidak Diketahui', 
                    $row['tanggal_lahir'] ?? 'Tidak Diketahui' 
                );
    
                $karyawan->setLembur($row['gaji_lembur'] ?? 0); // Default to 0 if not set
                $totalGaji = $karyawan->hitungGaji();
    
                echo "\n" . str_pad("ID", 10) . str_pad("Nama", 20) . str_pad("Jabatan", 15) . str_pad("Alamat", 15) . str_pad("Telepon", 15) . str_pad("Tempat Lahir", 15) . str_pad("Tanggal Lahir", 15) . "\n";
                echo str_repeat("=", 105) . "\n";
                echo str_pad($row['id'], 10) . 
                    str_pad($row['nama'] ?? 'Tidak Diketahui', 20) . 
                    str_pad($row['jabatan'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($row['alamat'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($row['telepon'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($row['tempat_lahir'] ?? 'Tidak Diketahui', 15) . 
                    str_pad($row['tanggal_lahir'] ?? 'Tidak Diketahui', 15) . "\n";
                echo str_repeat("=", 105) . "\n";
    
                echo "\n" . str_pad("Gaji Bulanan", 20) . str_pad("Gaji Lembur", 20) . str_pad("Total Gaji", 20) . "\n";
                echo str_repeat("=", 60) . "\n";
                echo str_pad("Rp" . number_format($row['gaji_bulanan'] ?? 0, 0, ',', '.'), 20) .
                    str_pad("Rp" . number_format($row['gaji_lembur'] ?? 0, 0, ',', '.'), 20) .
                    str_pad("Rp" . number_format($totalGaji, 0, ',', '.'), 20) . "\n";
                echo str_repeat("=", 60) . "\n";
            } else {
                echo "Karyawan dengan ID $id tidak ditemukan.\n";
            }
        } catch (PDOException $e) {
            echo "Query gagal: " . $e->getMessage() . "\n";
        }
    }

    public function editKaryawan($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT dk.id, dk.nama, dk.jabatan, dk.alamat, dk.telepon, dk.tempat_lahir, dk.tanggal_lahir 
                                        FROM Data_karyawan dk 
                                        WHERE dk.id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo "Karyawan dengan ID $id tidak ditemukan.\n";
                return;
            }

            echo "Informasi Karyawan:\n";
            echo "ID: " . (isset($row['id']) ? $row['id'] : 'Tidak Diketahui') . "\n";
            echo "Nama: " . (isset($row['nama']) ? $row['nama'] : 'Tidak Diketahui') . "\n";
            echo "Jabatan: " . (isset($row['jabatan']) ? $row['jabatan'] : 'Tidak Diketahui') . "\n";
            echo "Alamat: " . (isset($row['alamat']) ? $row['alamat'] : 'Tidak Diketahui') . "\n";
            echo "Telepon: " . (isset($row['telepon']) ? $row['telepon'] : 'Tidak Diketahui') . "\n";
            echo "Tempat Lahir: " . (isset($row['tempat_lahir']) ? $row['tempat_lahir'] : 'Tidak Diketahui') . "\n";
            echo "Tanggal Lahir: " . (isset($row['tanggal_lahir']) ? $row['tanggal_lahir'] : 'Tidak Diketahui') . "\n";

            echo "\n=== Pilih Informasi yang Ingin Diedit ===\n";
            echo "1. Nama\n";
            echo "2. Jabatan\n";
            echo "3. Alamat\n";
            echo "4. Telepon\n";
            echo "5. Tempat Lahir\n";
            echo "6. Tanggal Lahir\n";
            echo "7. Kembali\n";
            echo "Pilih opsi (1-7): ";
            $editPilihan = trim(fgets(STDIN));

            switch ($editPilihan) {
                case "1":
                    while (true) {
                        echo "Masukkan nama baru: ";
                        $namaBaru = trim(fgets(STDIN));
                        $stmtCheckNama = $this->pdo->prepare("SELECT COUNT(*) FROM Data_karyawan WHERE nama = :nama AND id != :id");
                        $stmtCheckNama->execute([':nama' => $namaBaru, ':id' => $id]);
                        $count = $stmtCheckNama->fetchColumn();

                        if ($count > 0) {
                            echo "Nama '$namaBaru' sudah digunakan oleh karyawan lain. Silakan masukkan nama yang berbeda.\n";
                        } else {
                            $row['nama'] = $namaBaru;
                            break; 
                        }
                    }
                    break;
                case "2":
                    echo "Masukkan jabatan baru: ";
                    $jabatanBaru = trim(fgets(STDIN));
                    $row['jabatan'] = $jabatanBaru;
                    break;
                case "3":
                    echo "Masukkan alamat baru: ";
                    $alamatBaru = trim(fgets(STDIN));
                    $row['alamat'] = $alamatBaru;
                    break;
                case "4":
                    echo "Masukkan telepon baru: ";
                    $teleponBaru = trim(fgets(STDIN));
                    $row['telepon'] = $teleponBaru;
                    break;
                case "5":
                    echo "Masukkan tempat lahir baru: ";
                    $tempatLahirBaru = trim(fgets(STDIN));
                    $row['tempat_lahir'] = $tempatLahirBaru;
                    break;
                case "6":
                    while (true) {
                        echo "Masukkan tanggal lahir baru (YYYY-MM-DD): ";
                        $tanggalLahirBaru = trim(fgets(STDIN));
                        if (KaryawanAbstract::validateTanggalLahir($tanggalLahirBaru)) {
                            $row['tanggal_lahir'] = $tanggalLahirBaru;
                            break;
                        } else {
                            echo "Tanggal Lahir tidak valid. Coba lagi.\n";
                        }
                    }
                    break;
                case "7":
                    echo "Kembali ke menu utama.\n";
                    return; 
                default:
                    echo "Pilihan tidak valid.\n";
                    return;
            }

            $stmtUpdate = $this->pdo->prepare("UPDATE Data_karyawan SET nama = :nama, jabatan = :jabatan, alamat = :alamat, telepon = :telepon, tempat_lahir = :tempat_lahir, tanggal_lahir = :tanggal_lahir WHERE id = :id");
            $stmtUpdate->execute([
                'nama' => $row['nama'],
                'jabatan' => $row['jabatan'],
                'alamat' => $row['alamat'],
                'telepon' => $row['telepon'],
                'tempat_lahir' => $row['tempat_lahir'],
                'tanggal_lahir' => $row['tanggal_lahir'],
                'id' => $id
            ]);
            
            $stmtUpdateGaji = $this->pdo->prepare("UPDATE Gaji_karyawan SET nama = :nama, jabatan = :jabatan, telepon = :telepon WHERE id = :id");
            $stmtUpdateGaji->execute([
                'nama' => $row['nama'],
                'jabatan' => $row['jabatan'],
                'telepon' => $row['telepon'],
                'id' => $id
            ]);
    
            echo "Informasi karyawan dengan ID $id berhasil diperbarui!\n";
        } catch (PDOException $e) { 
            echo "Query gagal: " . $e->getMessage() . "\n";
        }
    }

    public function hapusKaryawan($idHapus) {
        $idsArray = explode(',', $idHapus);
        $idsArray = array_map('trim', $idsArray);

        $placeholders = rtrim(str_repeat('?,', count($idsArray)), ','); // Membuat placeholder untuk query
        $stmt = $this->pdo->prepare("DELETE FROM Data_karyawan WHERE id IN ($placeholders)");

        $stmt->execute($idsArray);

        if ($stmt->rowCount() > 0) {
            echo "Karyawan dengan ID " . implode(', ', $idsArray) . " berhasil dihapus dari database.\n";
        } else {
            echo "Tidak ada karyawan yang ditemukan dengan ID " . implode(', ', $idsArray) . ".\n";
        }

        foreach ($idsArray as $id) {
            foreach ($this->karyawan as $index => $karyawan) {
                if ($karyawan->getId() == $id) {
                    unset($this->karyawan[$index]);
                    break;
                }
            }
        }
    }
}

$sistem = new Sistem();
while (true) {
    echo "\n=== Sistem Pengelolaan Karyawan ===\n";
    echo "1. Tampilkan Data Karyawan\n";
    echo "2. Tambah Karyawan Baru\n";
    echo "3. Cari Karyawan dengan ID\n";
    echo "4. Hapus Karyawan\n";
    echo "5. Edit Karyawan\n";
    echo "6. Kelola Gaji Karyawan\n";
    echo "7. Keluar\n";
    echo "Pilih opsi (1-7): ";
    $pilihan = trim(fgets(STDIN));

    switch ($pilihan) {
        case "1":
            $sistem->muatKaryawanDariDatabase();
            break;
        case "2":
            while (true) { // Use null coalescing operator
                echo "Masukkan Nama Karyawan: ";
                $nama = trim(fgets(STDIN));

                if (KaryawanAbstract::validateNamaKaryawan($sistem->pdo, $nama)) {
                    echo "Nama ini sudah terdaftar di database, Gunakan nama lain.\n";
                } else {
                    echo "Masukkan Jabatan: ";
                    $jabatan = trim(fgets(STDIN));
                    echo "Masukkan Gaji Bulanan: ";
                    $gajiBulanan = (float)trim(fgets(STDIN));
                    if ($gajiBulanan <= 0) {
                        echo "Gaji bulanan harus lebih besar dari 0.\n";
                        continue;
                    }
                    echo "Masukkan Alamat: ";
                    $alamat = trim(fgets(STDIN));
                    echo "Masukkan Telepon: ";
                    $telepon = trim(fgets(STDIN));
                    echo "Masukkan Tempat Lahir: ";
                    $tempatLahir = trim(fgets(STDIN));

                    while (true) {
                        echo "Mascukkan Tanggal Lahir (YYYY-MM-DD): ";
                        $tanggalLahir = trim(fgets(STDIN));

                        if (!KaryawanAbstract::validateTanggalLahir($tanggalLahir)) {
                            echo "Tanggal Lahir tidak valid. Coba lagi.\n";
                        } else {
                            break;
                        }
                    }
                    $karyawan = new Karyawan(null, $nama, $jabatan, $gajiBulanan, $alamat, $telepon, $tempatLahir, $tanggalLahir);
                    $sistem->tambahKaryawan($karyawan);
                    
                    echo "Karyawan baru berhasil ditambahkan!\n";
                    break; 
                }
            }
            break;  
        case "3":
            echo "Masukkan ID Karyawan yang ingin dicari: ";
            $idCari = trim(fgets(STDIN));
            $sistem->cariKaryawanBerdasarkanId($idCari);
            break;
        case "4":
            echo "Masukkan ID Karyawan untuk dihapus: ";
            $idHapus = trim(fgets(STDIN));
            $sistem->hapusKaryawan($idHapus);
            break;
        case "5":
            echo "Masukkan ID Karyawan yang ingin diedit: ";
            $idEdit = trim(fgets(STDIN));
            $sistem->editKaryawan($idEdit);
            break;
        case "6":
            while (true) {
                echo "\n=== Kelola Gaji Karyawan ===\n";
                echo "1. Tampilkan Gaji Karyawan\n";
                echo "2. Tambah & Kurangi Gaji Bulanan Karyawan\n";
                echo "3. Tambah Gaji Lembur Karyawan\n";
                echo "4. Proses Pembayaran Gaji Karyawan\n";
                echo "5. Kembali ke Menu Utama\n";
                echo "Pilih opsi (1-5): ";
                $gajiPilihan = trim(fgets(STDIN));
                
                switch ($gajiPilihan) {
                    case "1":
                        echo "Masukkan ID Karyawan: ";
                        $idKaryawan = trim(fgets(STDIN));
                        $sistem->cariKaryawanBerdasarkanId($idKaryawan);
                        break;
                    case "2":
                        echo "Masukkan ID Karyawan: ";
                        $idKaryawan = trim(fgets(STDIN));
                        
                        echo "Masukkan Jumlah Tambahan Gaji Bulanan: ";
                        $jumlahTambahanBulanan = (float)trim(fgets(STDIN));
                        
                        $stmt = $sistem->pdo->prepare("SELECT gaji_bulanan FROM Gaji_karyawan WHERE id = :id");
                        $stmt->execute(['id' => $idKaryawan]);
                        $row = $stmt->fetch();
                
                        if ($row) {
                            $gajiBulananLama = $row['gaji_bulanan'];
                            $gajiBulananBaru = $gajiBulananLama + $jumlahTambahanBulanan;
                
                            $stmtUpdate = $sistem->pdo->prepare("UPDATE Gaji_karyawan SET gaji_bulanan = :gaji_bulanan WHERE id = :id");
                            $stmtUpdate->execute(['gaji_bulanan' => $gajiBulananBaru, 'id' => $idKaryawan]);
                            echo "Gaji bulanan untuk karyawan dengan ID $idKaryawan berhasil diperbarui menjadi Rp" . number_format($gajiBulananBaru, 0, ',', '.') . ".\n";
                        } else {
                            echo "Karyawan dengan ID $idKaryawan tidak ditemukan.\n";
                        }
                
                        echo "Masukkan Persentase Pengurangan Gaji Bulanan (misal: 10 untuk -10%): ";
                        $persentasePengurangan = (float)trim(fgets(STDIN));
                        
                        $pengurangan = $gajiBulananLama * ($persentasePengurangan / 100);
                        $gajiBulananBaru -= $pengurangan;

                        if ($gajiBulananBaru < 0) {
                            echo "Pengurangan gaji tidak dapat menghasilkan nilai negatif.\n";
                            break;
                        }
                
                        $stmtUpdate = $sistem->pdo->prepare("UPDATE Gaji_karyawan SET gaji_bulanan = :gaji_bulanan WHERE id = :id");
                        $stmtUpdate->execute(['gaji_bulanan' => $gajiBulananBaru, 'id' => $idKaryawan]);
                        echo "Gaji bulanan untuk karyawan dengan ID $idKaryawan berhasil dikurangi menjadi Rp" . number_format($gajiBulananBaru, 0, ',', '.') . ".\n";
                        break;
                    case "3":
                        echo "Masukkan ID Karyawan: ";
                        $idKaryawan = trim(fgets(STDIN));
                        echo "Masukkan Jumlah Jam Lembur: ";
                        $jamLembur = (float)trim(fgets(STDIN));
                        
                        $lemburPerJam = 50000; 
                        $gajiLemburBaru = $jamLembur * $lemburPerJam;
                
                        $stmt = $sistem->pdo->prepare("SELECT gaji_lembur FROM Gaji_karyawan WHERE id = :id");
                        $stmt->execute(['id' => $idKaryawan]);
                        $row = $stmt->fetch();
                
                        if ($row) {
                            $gajiLemburLama = $row['gaji_lembur'];
                            $totalGajiLembur = $gajiLemburLama + $gajiLemburBaru;
                
                            $stmtUpdate = $sistem->pdo->prepare("UPDATE Gaji_karyawan SET gaji_lembur = :gaji_lembur WHERE id = :id");
                            $stmtUpdate->execute(['gaji_lembur' => $totalGajiLembur, 'id' => $idKaryawan]);
                            echo "Gaji lembur untuk karyawan dengan ID $idKaryawan berhasil diperbarui menjadi Rp" . number_format($totalGajiLembur, 0, ',', '.') . ".\n";
                        } else {
                            echo "Karyawan dengan ID $idKaryawan tidak ditemukan.\n";
                        }
                        break;
                    case "4":
                        echo "Masukkan ID Karyawan untuk memproses pembayaran gaji: ";
                        $idKaryawan = trim(fgets(STDIN));
                        $sistem->cariKaryawanBerdasarkanId($idKaryawan);

$stmt = $sistem->pdo->prepare("SELECT * FROM Gaji_karyawan WHERE id = :id");
$stmt->execute(['id' => $idKaryawan]);
$row = $stmt->fetch();

if ($row) {
    $karyawan = new Karyawan(
        $row['id'],
        $row['nama'] ?? 'Tidak Diketahui', // Use null coalescing operator
        $row['jabatan'] ?? 'Tidak Diketahui', // Use null coalescing operator
        $row['gaji_bulanan'] ?? 0, // Default to 0 if not set
        $row['alamat'] ?? 'Tidak Diketahui', // Use null coalescing operator
        $row['telepon'] ?? 'Tidak Diketahui', // Use null coalescing operator
        $row['tempat_lahir'] ?? 'Tidak Diketahui', // Use null coalescing operator
        $row['tanggal_lahir'] ?? 'Tidak Diketahui' // Use null coalescing operator
    );

    $karyawan->setLembur($row['gaji_lembur'] ?? 0); // Default to 0 if not set
    $karyawan->prosesPembayaranGaji($sistem->pdo); // Panggil metode untuk memproses pembayaran gaji
} else {
    echo "Karyawan dengan ID $idKaryawan tidak ditemukan.\n";
}
break;

case "5":
    echo "Kembali ke menu utama.\n";
    break;

default:
    echo "Pilihan tidak valid.\n";
}

if ($gajiPilihan == "5") 
    break;

}
break;

case "7":
    echo "Keluar dari sistem.\n";
    exit;

default:
    echo "Pilihan tidak valid.\n";
}
    }
