/* Reaksi & ulasan di popup detail karya.
   Dipanggil dari index.php:  StellaInteraksi.buka(kartu)
   Semua teks dari server dimasukkan lewat textContent (aman dari XSS). */
(function () {
    var popup = document.getElementById('projectPopup');
    if (!popup) { return; }

    var URL_API = 'project_interaksi.php';
    var csrf = popup.dataset.csrf || '';

    var bar        = document.getElementById('reaksiBar');
    var catatan    = document.getElementById('reaksiCatatan');
    var ringkasan  = document.getElementById('ulasanRingkasan');
    var form       = document.getElementById('ulasanForm');
    var bintangBox = document.getElementById('bintangPilih');
    var komentar   = document.getElementById('ulasanKomentar');
    var hitung     = document.getElementById('ulasanHitung');
    var tombolKirim = document.getElementById('ulasanKirim');
    var pesan      = document.getElementById('ulasanPesan');
    var daftar     = document.getElementById('ulasanList');

    var idKarya = 0;      // karya yang sedang dibuka
    var kartu = null;     // elemen kartu di galeri (untuk update angka di kartu)
    var rating = 0;       // bintang yang dipilih di form
    var sedangKirim = false;
    var permintaanKe = 0; // untuk mengabaikan jawaban yang sudah usang

    /* ---------- util ---------- */
    function tampilPesan(teks, error) {
        pesan.textContent = teks || '';
        pesan.className = 'ulasan-pesan' + (teks ? (error ? ' error' : ' ok') : '');
    }

    function bintangTeks(n) {
        return '\u2605'.repeat(n) + '\u2606'.repeat(5 - n);
    }

    function setRating(n) {
        rating = n;
        bintangBox.querySelectorAll('.bintang-btn').forEach(function (b) {
            b.classList.toggle('aktif', parseInt(b.dataset.nilai, 10) <= n);
        });
    }

    /* ---------- kirim ke server ---------- */
    function kirim(data) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });

        return fetch(URL_API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().catch(function () {
                    return { ok: false, pesan: 'Terjadi kesalahan (kode ' + res.status + ').' };
                });
            })
            .catch(function () {
                return { ok: false, pesan: 'Tidak bisa terhubung ke server.' };
            });
    }

    function muat() {
        var ke = ++permintaanKe;
        fetch(URL_API + '?id=' + encodeURIComponent(idKarya), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (ke !== permintaanKe) { return; }
                if (!data.ok) { tampilPesan(data.pesan, true); return; }
                gambar(data, true);
            })
            .catch(function () {
                if (ke === permintaanKe) { tampilPesan('Gagal memuat reaksi dan ulasan.', true); }
            });
    }

    /* ---------- tampilkan data ---------- */
    function gambar(data, isiFormDariServer) {
        // Reaksi
        bar.querySelectorAll('.reaksi-btn').forEach(function (btn) {
            var jenis = btn.dataset.reaction;
            btn.querySelector('.reaksi-jml').textContent = data.reaksi.jumlah[jenis] || 0;
            btn.classList.toggle('aktif', data.reaksi.saya === jenis);
            btn.disabled = data.pemilik;
        });
        catatan.style.display = data.pemilik ? 'block' : 'none';

        // Ringkasan ulasan
        if (data.ulasan.jumlah > 0) {
            ringkasan.textContent = '\u2605 ' + data.ulasan.rata.toFixed(1) + ' \u00b7 ' + data.ulasan.jumlah + ' ulasan';
        } else {
            ringkasan.textContent = '\u00b7 belum ada ulasan';
        }

        // Form ulasan (disembunyikan untuk pemilik karya)
        form.style.display = data.pemilik ? 'none' : 'block';
        if (isiFormDariServer && !data.pemilik) {
            var saya = data.ulasan.saya;
            setRating(saya ? saya.rating : 0);
            komentar.value = saya ? saya.komentar : '';
            hitung.textContent = komentar.value.length;
            tombolKirim.textContent = saya ? 'Perbarui Ulasan' : 'Kirim Ulasan';
            tampilPesan('');
        } else if (!data.pemilik) {
            tombolKirim.textContent = data.ulasan.saya ? 'Perbarui Ulasan' : 'Kirim Ulasan';
        }

        // Daftar ulasan
        daftar.textContent = '';
        data.ulasan.daftar.forEach(function (u) {
            var item = document.createElement('div');
            item.className = 'ulasan-item';

            var kepala = document.createElement('div');
            kepala.className = 'ulasan-kepala';

            var nama = document.createElement('b');
            nama.textContent = u.nama + (u.milik ? ' (kamu)' : '');
            kepala.appendChild(nama);

            var bintang = document.createElement('span');
            bintang.className = 'ulasan-bintang';
            bintang.textContent = bintangTeks(u.rating);
            kepala.appendChild(bintang);

            item.appendChild(kepala);

            var isi = document.createElement('p');
            isi.className = 'ulasan-isi';
            isi.textContent = u.komentar;
            item.appendChild(isi);

            var kaki = document.createElement('div');
            kaki.className = 'ulasan-kaki';
            var tgl = document.createElement('span');
            tgl.textContent = u.tanggal;
            kaki.appendChild(tgl);

            if (u.bisa_hapus) {
                var hapus = document.createElement('button');
                hapus.type = 'button';
                hapus.className = 'ulasan-hapus';
                hapus.textContent = 'Hapus';
                hapus.addEventListener('click', function () { hapusUlasan(u.id); });
                kaki.appendChild(hapus);
            }
            item.appendChild(kaki);
            daftar.appendChild(item);
        });

        // Angka di kartu galeri ikut diperbarui
        if (kartu) {
            var sr = kartu.querySelector('.stat-reaksi');
            var su = kartu.querySelector('.stat-ulasan');
            var st = kartu.querySelector('.stat-rating');
            if (sr) { sr.textContent = data.reaksi.total; }
            if (su) { su.textContent = data.ulasan.jumlah; }
            if (st) { st.textContent = data.ulasan.rata !== null ? data.ulasan.rata.toFixed(1) : '-'; }
        }
    }

    /* ---------- aksi pengguna ---------- */
    function pasangReaksi(jenis) {
        kirim({ aksi: 'reaksi', id: idKarya, reaction: jenis }).then(function (data) {
            if (!data.ok) { tampilPesan(data.pesan, true); return; }
            tampilPesan('');
            gambar(data, false);
        });
    }

    function hapusUlasan(reviewId) {
        if (!confirm('Hapus ulasan ini?')) { return; }
        kirim({ aksi: 'ulasan_hapus', review_id: reviewId }).then(function (data) {
            if (!data.ok) { tampilPesan(data.pesan, true); return; }
            // Jika yang dihapus ulasan milik sendiri, kosongkan form
            gambar(data, true);
        });
    }

    bar.querySelectorAll('.reaksi-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { pasangReaksi(btn.dataset.reaction); });
    });

    bintangBox.querySelectorAll('.bintang-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { setRating(parseInt(btn.dataset.nilai, 10)); });
    });

    komentar.addEventListener('input', function () { hitung.textContent = komentar.value.length; });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (sedangKirim) { return; }

        if (rating < 1) { tampilPesan('Pilih rating bintang dulu.', true); return; }
        if (komentar.value.trim().length < 3) { tampilPesan('Ulasan minimal 3 karakter.', true); return; }

        sedangKirim = true;
        tombolKirim.disabled = true;
        kirim({ aksi: 'ulasan_simpan', id: idKarya, rating: rating, komentar: komentar.value })
            .then(function (data) {
                sedangKirim = false;
                tombolKirim.disabled = false;
                if (!data.ok) { tampilPesan(data.pesan, true); return; }
                gambar(data, false);
                tampilPesan('Ulasan tersimpan.', false);
            });
    });

    /* ---------- dipanggil saat popup dibuka ---------- */
    window.StellaInteraksi = {
        buka: function (kartuDipilih) {
            kartu = kartuDipilih;
            idKarya = parseInt(kartuDipilih.dataset.id, 10);

            // Kosongkan dulu supaya data karya sebelumnya tidak sempat terlihat
            bar.querySelectorAll('.reaksi-jml').forEach(function (el) { el.textContent = '0'; });
            bar.querySelectorAll('.reaksi-btn').forEach(function (b) {
                b.classList.remove('aktif');
                b.disabled = kartuDipilih.dataset.milik === '1';
            });
            catatan.style.display = kartuDipilih.dataset.milik === '1' ? 'block' : 'none';
            ringkasan.textContent = '';
            daftar.textContent = '';
            form.style.display = kartuDipilih.dataset.milik === '1' ? 'none' : 'block';
            setRating(0);
            komentar.value = '';
            hitung.textContent = '0';
            tampilPesan('');

            muat();
        }
    };
})();
