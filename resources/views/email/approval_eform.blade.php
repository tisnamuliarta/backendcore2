<table style="border-collapse: collapse;width: 100%;">
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tipe Surat</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->paper_type }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Nomor Surat</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->paper_no }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tanggal Surat</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->paper_date }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Perusahaan</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->company }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Nama</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->user_name }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">NIK</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->id_card }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Jabatan</td>
        <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->occupation }}</td>
    </tr>

    @if ($form->paper_alias == 'sik')
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tanggal Keluar Kawasan</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->date_out }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tujuan</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->destination }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Keterangan</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->reason }}</td>
        </tr>
    @endif

    @if ($form->paper_alias == 'sim')
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tanggal Masuk Kawasan</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->date_in }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Keterangan</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->reason }}</td>
        </tr>
    @endif

    @if ($form->paper_alias == 'srk' || $form->paper_alias == 'srm')
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Departemen</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->department }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Pembayaran</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->payment }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Tanggal Swab</td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->swab_date }}</td>
        </tr>
        @if(isset($form->reason_swab))
            <tr>
                <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Keterangan</td>
                <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $form->reason_swab }}</td>
            </tr>
        @endif
    @endif
</table>
