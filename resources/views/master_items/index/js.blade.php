<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    var start_date = '';
    var end_date = '';
    var data_per_fetch = 500;
    var data_fetched = 0;
    var dataTableObj;

    $(document).ready(function() {
        // initialize DataTable and set default ordering to Kode column (index 1)
        dataTableObj = $('#table').DataTable({
            searching: false,
            order: [[1, 'desc']], // sort by Kode column by default
            columnDefs: [
                {
                    targets: 0, // Image column
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (!data || data === 'No Image') {
                            return '<span class="text-muted">No Image</span>';
                        }
                        // For ordering/search, return a text representation in 'type' other than 'display'
                        if (type === 'display' || type === 'filter') {
                            return `<img src="/storage/master_items/${data}" style="max-width:50px; max-height:50px;">`;
                        }
                        return data;
                    }
                },
                {
                    targets: 4, // Harga Beli column (numeric)
                    render: $.fn.dataTable.render.number(',', '.', 0, '')
                },
                {
                    targets: 5, // Harga Jual column (numeric)
                    render: $.fn.dataTable.render.number(',', '.', 0, '')
                },
                {
                    targets: 7, // View button
                    orderable: false,
                    searchable: false
                }
            ]
        });

        // fetch initial data
        getData();
    });

    $('.btn-get-data').click(function() {
        getData();
    });

    function getData(){
        $('#loading-filter').show();
        // clear existing rows without destroying DataTable instance
        dataTableObj.clear();

        // Read inputs and normalize
        var filter_kode = $('#filter-kode').val();
        var filter_nama = $('#filter-nama').val();
        var filter_harga_min = $('#filter-harga-min').val();
        var filter_harga_max = $('#filter-harga-max').val();

        // Convert empty strings to null so backend can handle correctly
        filter_kode = (typeof filter_kode === 'string' && filter_kode.trim() === '') ? null : filter_kode;
        filter_nama = (typeof filter_nama === 'string' && filter_nama.trim() === '') ? null : filter_nama;
        filter_harga_min = (filter_harga_min === '') ? null : filter_harga_min;
        filter_harga_max = (filter_harga_max === '') ? null : filter_harga_max;

        // Basic client-side numeric validation (optional)
        if (filter_harga_min !== null && isNaN(filter_harga_min)) {
            alert('Harga Min harus berupa angka');
            $('#loading-filter').hide();
            return;
        }
        if (filter_harga_max !== null && isNaN(filter_harga_max)) {
            alert('Harga Max harus berupa angka');
            $('#loading-filter').hide();
            return;
        }

        $.ajax({
            url: '{{ url("master-items/search") }}',
            method: 'GET',
            dataType: 'json',
            data: {
                kode: filter_kode,
                nama: filter_nama,
                hargamin: filter_harga_min,
                hargamax: filter_harga_max
            },
            success: function(results) {
                if (!results || results.status !== 200) {
                    var msg = (results && results.message) ? results.message : 'Unknown error';
                    alert('Error: ' + msg);
                    $('#loading-filter').hide();
                    return;
                }

                // Transform results into arrays for DataTables
                var data = results.data.map(function(item) {
                    var harga_jual = 0;
                    if (typeof item.harga_beli === 'number' && typeof item.laba === 'number') {
                        harga_jual = Math.round(item.harga_beli + (item.harga_beli * item.laba / 100));
                    } else {
                        // If backend returned strings, try to parse
                        var hb = parseFloat(item.harga_beli) || 0;
                        var laba = parseFloat(item.laba) || 0;
                        harga_jual = Math.round(hb + (hb * laba / 100));
                    }

                    var imageColumn = item.image || 'No Image';
                    var viewBtn = `<a href="{{ url('master-items/view/') }}/${item.kode}" class="btn btn-primary btn-sm">View</a>`;

                    return [
                        imageColumn,      // 0 - image (string filename or 'No Image')
                        item.kode,        // 1 - kode
                        item.nama,        // 2 - nama
                        item.jenis,       // 3 - jenis
                        item.harga_beli,  // 4 - harga_beli (numeric)
                        harga_jual,       // 5 - harga_jual (numeric)
                        item.supplier,    // 6 - supplier
                        viewBtn           // 7 - view button
                    ];
                });

                // Add rows and redraw table (DataTables will apply column renderers and ordering)
                dataTableObj.rows.add(data).draw(false);
                $('#loading-filter').hide();
            },
            error: function(xhr, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown, xhr.responseText);
                try {
                    var resp = JSON.parse(xhr.responseText);
                    alert('Error: ' + (resp.message || resp.error || errorThrown));
                } catch (e) {
                    alert('Internal Server Error');
                }
                $('#loading-filter').hide();
            }
        });
    }
</script>