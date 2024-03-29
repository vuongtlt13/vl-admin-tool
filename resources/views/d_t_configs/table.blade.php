@push('css')
    @include('layouts.datatables_css')
@endpush

<div class="card" style="overflow-y: auto;">
    <div class="card-body">
        {!! $dataTable->table(['width' => '100%', 'class' => 'table table-bordered table-hover myDataTable', 'id' => 'dTConfig-datatable']) !!}
    </div>
</div>

@push('scripts')
    @include('layouts.datatables_js')
    @include('vl-admin-tool::d_t_configs.toolbar_js')
    {!! $dataTable->scripts() !!}

    <script type="text/javascript">
        var dTConfigSelectedRows = [];
        var dTConfigTable = null;
    </script>

    <script type="text/javascript">
        $(document).ready(function() {
            dTConfigTable = $('#dTConfig-datatable').DataTable();
            initDatatableEvent('#dTConfig-datatable', dTConfigSelectedRows);
        });
    </script>
@endpush
