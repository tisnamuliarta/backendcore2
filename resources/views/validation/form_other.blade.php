<p class="text-center" style="margin-top: 12px">
    <img src="{{ asset('images/logo.png') }}" alt="" style="width: 200px">
</p>
<br/>

<p class="text-center">

<h3 class="text-center">{{ $paper->paper_name }}</h3>

<p class="text-center">
    <strong>Nomor: {{ $paper->paper_no }}</strong>
</p>

<table>
    <tr>
        <td>Tanggal Surat</td>
        <td> : </td>
        <td>{{ $paper->paper_date }}</td>
    </tr>
    <tr>
        <td>Perusahaan</td>
        <td> : </td>
        <td>{{ $paper->company }}</td>
    </tr>
    <tr>
        <td>Nama</td>
        <td> : </td>
        <td>{{ $paper->user_name }}</td>
    </tr>
    <tr>
        <td>NIK</td>
        <td> : </td>
        <td>{{ $paper->id_card }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td> : </td>
        <td>{{ $paper->occupation }}</td>
    </tr>

    @if ($paper->paper_alias == 'sik')
        <tr>
            <td>Tanggal Keluar Kawasan</td>
            <td> : </td>
            <td>{{ $paper->date_out }}</td>
        </tr>
        <tr>
            <td>Tujuan</td>
            <td> : </td>
            <td>{{ $paper->destination }}</td>
        </tr>
    @endif

    @if ($paper->paper_alias == 'sim')
        <tr>
            <td>Tanggal Masuk Kawasan</td>
            <td> : </td>
            <td>{{ $paper->date_in }}</td>
        </tr>
    @endif

    @if ($paper->paper_alias == 'srk' || $paper->paper_alias == 'srm')
        <tr>
            <td>Departemen</td>
            <td> : </td>
            <td>{{ $paper->department }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td> : </td>
            <td>{{ $paper->address }}</td>
        </tr>
        <tr>
            <td>No HP</td>
            <td> : </td>
            <td>{{ $paper->no_hp }}</td>
        </tr>
        <tr>
            <td>Pembayaran</td>
            <td> : </td>
            <td>{{ $paper->payment }}</td>
        </tr>
    @endif


    <tr>
        <td>Keterangan</td>
        <td> : </td>
        <td>{{ $paper->reason }}</td>
    </tr>
</table>

<br>
<br>
<br>

<p class="text-center" style="margin-bottom: -2px">
    <small>
        <b>PT. Indonesia Morowali Industrial Park</b>
    </small>
</p>
<p class="text-center" style="margin-bottom: -2px">
    <small>
        Gedung IMIP, Jl. Batu Mulia No 8, Taman Meruya Hilir Blok N, Meruya Utara, Kembangan, Kota Jakarta Barat, DKI
        Jakarta
    </small>
</p>
<p class="text-center">
    <small>
        Phone : +62 21 2941 9688 │Fax : +62 21 2941 9696 │E-mail : secretariat@imip.co.id │www.imip.co.id
    </small>
</p>

