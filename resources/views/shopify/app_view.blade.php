<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1">
    <title>Home Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="{{ asset('public/css/style.css') }}" rel="stylesheet" />
    {{-- <link href="{{ env('APP_URL') }}css/style.css" rel="stylesheet" /> --}}

</head>
<body class="mt-4 mb-4 container">
@php

function get_authentication_url(){
    $redirect_url_for_token = secure_url('generate_token').'?app_version=0.2';
    $api_key =  env('SHOPIFY_API_KEY');
    $scopes =  "read_products,read_product_listings,read_themes,write_themes";

    // Build approval URL
    $install_url = "https://" . $_GET['shop'] . "/admin/oauth/authorize?client_id=" . $api_key . "&scope=" . $scopes . "&redirect_uri=" . $redirect_url_for_token;
    return $install_url;
}
@endphp
    @include('shopify.app_bridge')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                @include('shopify.tabs')
                <div class="tab-content" id="myTabContent">
                    @include('shopify.settings')
                    @if($store->shop_url == "product-table-33.myshopify.com" || $store->shop_url == "merrillsoft.myshopify.com")
                    @include('shopify.hide_products')
                    @endif
                    @include('shopify.plan')
                    @include('shopify.support')
                    @include('shopify.how_to_use')
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script>

    // Variant Table App

    $(document).ready(function() {

        $('.update-scope').click(UpdatePermission);
        $('#btn_styling_enable').click(function(){
            if($('.btn_styling').is(':visible')){
                $('.btn_styling').hide();
                $('#btn_bg').attr('required',false);
                $('#btn_color').attr('required',false);
                $('#btn_text').attr('required',false);
            }
            else{
                $('.btn_styling').show();
                $('#btn_bg').attr('required',true);
                $('#btn_color').attr('required',true);
                $('#btn_text').attr('required',true);
            }
        });
        $('#product_feature_enable').click(function(){
            if($('.products').is(':visible')){
                $('.products').hide();
                $('#check_product').attr('required',false);
            }
            else{
                $('.products').show();
                $('#check_product').attr('required',true);
            }
        });

        $('input[name=check_product]').change(function(){
            if($('#check_product_2').is(':checked')){
                $('#include_products').find('option').get(0).remove();
                $('#include_products').attr('multiple',true);
                $('#include_products').select2();
                $('#include_products').attr('disabled',false);
                $('#include_products').attr('required',true);
                $('#include_products').val('');
                $('#include_products').trigger('change');
            }
            else{
                $('#include_products').attr('disabled',true);
                $("#include_products").prepend("<option value='' selected>All Products</option>");
                $('#include_products').select2('destroy');
                $('#include_products').attr('required',false);
                $('#include_products').attr('multiple',false);
            }
        });
        $('#active_column_variants').select2({
            placeholder: "Active Columns",
            allowClear: true
        });
        $('#active_column_mobile').select2({
            placeholder: "Active Columns",
            allowClear: true
        });
        @if(isset($variantSettings->product_feature_enable) && $variantSettings->product_feature_enable == 1 )
            @if(!empty($variantSettings->include_products))
                var includeProducts = @json($variantSettings->include_products);
                $('#include_products').find('option').get(0).remove();
                $('#include_products').attr('multiple',true);
                $('#include_products').select2();
                $('#include_products').attr('disabled',false);
                $('#include_products').attr('required',true);
                $('#include_products').val(includeProducts);
                $('#include_products').trigger('change');
            @endif
        @endif
        // Variant Table App

        $('#styling_enable').click(function(){
            if($('.styling').is(':visible')){
                $('.styling').hide();
            }
            else{
                $('.styling').show();
            }
        });
        {{-- $('#table_styling_enable').click(function(){
            if($('.table_styling').is(':visible')){
                $('.table_styling').hide();
                $('#table_width').attr('required',false);
                $('#table_height').attr('required',false);
            }
            else{
                $('.table_styling').show();
                $('#table_width').attr('required',true);
                $('#table_height').attr('required',true);
            }
        }); --}}
        $('#image_styling_enable').click(function(){
            if($('.image_styling').is(':visible')){
                $('.image_styling').hide();
                $('#width').attr('required',false);
                $('#height').attr('required',false);
            }
            else{
                $('.image_styling').show();
                $('#width').attr('required',true);
                $('#height').attr('required',true);
            }
        });
        $('#font_styling_enable').click(function(){
            if($('.font_styling').is(':visible')){
                $('.font_styling').hide();
                $('#fontsize').attr('required',false);
            }
            else{
                $('.font_styling').show();
                $('#fontsize').attr('required',true);
            }
        });
        $('#active_column').select2({
            placeholder: "Active Columns",
            allowClear: true
        });
        $("#active_column").on("select2:select", function (evt) {
            var element = evt.params.data.element;
            var $element = $(element);

            $element.detach();
            $(this).append($element);
            $(this).trigger("change");
        });
        @if(isset($settings->active_column_mobile))
        var activeColumnData = @json($settings->active_column);
        $("#active_column").val(activeColumnData).trigger('change');
        var options = [];
        for (var i = 0; i < activeColumnData.length; i++) {
            options.push($("#active_column option[value=" + activeColumnData[i] + "]"));
        }
        $("#active_column").append(options).trigger('change');
        @endif
        $("#active_column_mobile").on("select2:select", function (evt) {
            var element = evt.params.data.element;
            var $element = $(element);

            $element.detach();
            $(this).append($element);
            $(this).trigger("change");
        });
        @if(isset($settings->active_column_mobile))
            var activeMobileColumnData = @json($settings->active_column_mobile);
            $("#active_column_mobile").val(activeMobileColumnData).trigger('change');
            var options = [];
            for (var i = 0; i < activeMobileColumnData.length; i++) {
                options.push($("#active_column_mobile option[value=" + activeMobileColumnData[i] + "]"));
            }
            $("#active_column_mobile").append(options).trigger('change');
        @endif

        $('#exclude_collections').select2({
            placeholder: "Exclude Collections",
            allowClear: true
        });
        @if($settings && !empty($settings->exclude_collections))
            var exclude_collections = @json($settings->exclude_collections);
            $('#exclude_collections').val(exclude_collections).trigger('change');
        @endif
        var shop = "{{ $store->shop_url }}";
        @if($store->current_charge_id != null)
        $.ajax({
            url: "get_current_charge_status",
            type: 'POST',
            data: {
                "shop": shop,
            },
            success: function (response){
                if(response.recurring_application_charge.status != 'active'){
                    $("<br><span>Previous Charge Status = <strong>"+response.recurring_application_charge.status+"</strong></span>").insertAfter(".change-plan[data-plan_id={{ $store->plan_id }}]");
                }
            }
        });
        @endif
    });
    $(".edit").click(function(){
        var id = $(this).data("id");
        var shop = "{{ $store->shop_url }}";
        $.ajax({
            url: "update_status",
            type: 'POST',
            data: {
                "id": id,
                "shop": shop,
            },
            success: function (){
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        });
    });
    $("#cancel_charge_id").click(function(){

        var shop = "{{ $store->shop_url }}";
        $.ajax({
            url: "cancel_charge",
            type: 'POST',
            data: {
                "shop": shop,
            },
            success: function (response){
                //console.log(response);
                window.parent.location.href = response;
            }
        });
    });
    $(".delete").click(function(){
        Swal.fire({
            title: 'Are you sure?',
            text: "Do you wan't to delete?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire(
                    'Specific Table Removed Successfully!',
                    'Your specific table has been removed.',
                    'success'
                    )
                var id = $(this).data("id");
                var shop = "{{ $store->shop_url }}";
                $.ajax({
                    url: "delete_specific_table",
                    type: 'POST',
                    data: {
                        "id": id,
                        "shop": shop,
                    },
                    success: function (){
                        setTimeout(function() {
                            window.location.href += "&page=SpecificTable";
                        }, 2000);
                    }
                });
            }
        })

    });

    function changeCollection(collection) {
        var title = collection.options[collection.selectedIndex].text;
        $("#title").val(title);
    }

    $(".edit").click(function(){
        var shop = "{{ $store->shop_url }}";
        var id = $(this).data("id");

        $.ajax({
            url: "get_specific_table",
            type: 'POST',
            data: {
                "shop": shop,
                "id": id,
            },
            success: function (response){
                console.log(response.collection_id);
                $('#title').val(response.title);
                $("<input type='hidden' name='specific_id' value='"+response.id+"' />").insertAfter("#title");
                $('#url').val(response.url);
                $('#collection').val(response.collection_id).change();
                $('#formSpecificTable').attr('action','update_specific_table');
                $('.create').html('Update');
            }
        });
    });
    $(".change-plan").click(function(){

        let changePlanBtn = $(this);
        let planId  = $(this).attr('data-plan_id');
        let is_trial  = $(this).attr('data-is_trial');
        createCharge( planId , changePlanBtn,is_trial );
    });

    function createCharge( planId, planBtn,is_trial = false ){
        const params = new Proxy(new URLSearchParams(window.location.search), {
            get: (searchParams, prop) => searchParams.get(prop),
        });
        let shop_url_param = "?shop="+params.shop;

        $.ajax({
            method: "GET",
            url: '/create_charge/'+planId+shop_url_param+'&is_trial='+is_trial,
            success: function( response ){
                //   planBtn.next('.change-plan').prop('href',response.response.recurring_application_charge.confirmation_url);
                window.top.location.href = response.response.recurring_application_charge.confirmation_url;
            },
            error: function( jqXHR, textStatus ){
                    console.log('errr');
            }
        });
    }

function UpdatePermission(){
    let permissionsBtn = $(this);
    let permissionsUrl = permissionsBtn.data('url');
    window.top.location.href = permissionsUrl;
}
</script>
</body>
</html>
