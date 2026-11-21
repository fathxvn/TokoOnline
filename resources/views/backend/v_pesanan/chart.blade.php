@extends('backend.v_layouts.app')
@section('content')
<div class="card"><div class="card-body">
    <h4 class="card-title">Jumlah Pesanan per Bulan</h4>
    <p>Menghitung transaksi Paid, Kirim, dan Selesai berdasarkan tanggal pembuatan pesanan. Keranjang pending tidak dihitung.</p>
    <form method="GET" action="{{ route('backend.pesanan.chart') }}" class="mb-3">
        <label for="year">Tahun</label><input id="year" type="number" name="year" class="form-control mb-2" min="2000" max="2100" value="{{ $year }}" required>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>
    @if(array_sum($counts) === 0)<p>Belum ada transaksi pada tahun ini.</p>@endif
    <div id="monthly-orders" role="img" aria-label="Grafik jumlah pesanan per bulan tahun {{ $year }}" style="width:100%;height:280px"></div>
    <div class="table-responsive"><table class="table table-bordered">
        <thead><tr><th>Bulan</th><th>Jumlah transaksi</th></tr></thead><tbody>
        @foreach($counts as $month => $count)<tr><td>{{ ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$month - 1] }}</td><td>{{ $count }}</td></tr>@endforeach
        </tbody>
    </table></div>
</div></div>
@endsection
@push('scripts')
<script src="{{ asset('backend/libs/flot/jquery.flot.js') }}"></script>
<script src="{{ asset('backend/libs/flot/jquery.flot.resize.js') }}"></script>
<script>
jQuery(function ($) {
    var counts = @json(array_values($counts));
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $.plot($('#monthly-orders'), [{data: counts.map(function (value, index) { return [index + 1, value]; }), color: '#2255a4'}], {
        series: {bars: {show: true, barWidth: 0.6, align: 'center'}},
        xaxis: {ticks: months.map(function (month, index) { return [index + 1, month]; }), min: 0.5, max: 12.5},
        yaxis: {min: 0, minTickSize: 1, tickDecimals: 0}, grid: {borderWidth: 1}
    });
});
</script>
@endpush
