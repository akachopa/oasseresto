import ApexCharts from 'apexcharts';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

window.ApexCharts = ApexCharts;
window.DataTable = DataTable;

const DARK_KEY = 'oasse.theme';

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    localStorage.setItem(DARK_KEY, theme);
}

window.oasseToggleTheme = () => {
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
};

applyTheme(
    localStorage.getItem(DARK_KEY) ??
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
);

/**
 * DataTables server-side (PLAN 49): kolom pertama No, kolom terakhir Aksi.
 * Setiap tabel cukup menandai dirinya dengan data-oasse-table dan
 * data-columns, sisanya diseragamkan di sini.
 */
function bootTables(root = document) {
    root.querySelectorAll('[data-oasse-table]:not([data-booted])').forEach((el) => {
        el.dataset.booted = '1';

        const columns = JSON.parse(el.dataset.columns);
        const order = el.dataset.order ? JSON.parse(el.dataset.order) : [[1, 'desc']];

        new DataTable(el, {
            processing: true,
            serverSide: true,
            responsive: true,
            searchDelay: 400,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order,
            ajax: {
                url: el.dataset.url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                data: (payload) => {
                    const filters = {};
                    document
                        .querySelectorAll(`[data-table-filter="${el.id}"]`)
                        .forEach((input) => {
                            filters[input.name] = input.value;
                        });
                    payload.filters = filters;

                    return payload;
                },
            },
            columns: [
                {
                    data: null,
                    title: 'No',
                    orderable: false,
                    searchable: false,
                    width: '48px',
                    className: 'text-muted',
                    render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1,
                },
                ...columns,
                {
                    data: 'aksi',
                    title: 'Aksi',
                    orderable: false,
                    searchable: false,
                    className: 'text-right whitespace-nowrap',
                },
            ],
            language: {
                emptyTable: 'Belum ada data',
                zeroRecords: 'Tidak ada data yang cocok',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total)',
                lengthMenu: '_MENU_ baris',
                loadingRecords: 'Memuat...',
                processing: 'Memproses...',
                search: '',
                searchPlaceholder: 'Cari...',
                paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
            },
        });

        document.querySelectorAll(`[data-table-filter="${el.id}"]`).forEach((input) => {
            input.addEventListener('change', () => DataTable.tables({ api: true }).ajax.reload());
        });
    });
}

document.addEventListener('DOMContentLoaded', () => bootTables());
document.addEventListener('livewire:navigated', () => bootTables());
