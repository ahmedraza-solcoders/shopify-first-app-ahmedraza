@php
$value = true;
$variantValue = true;
if (isset($_GET['page'])){
    if ($_GET['page'] == 'SpecificTable' && $store->plan_id != 1){
        $value = false;
    }
}
if (isset($_GET['page'])){
    if ($_GET['page'] == 'VariantTable' && $store->plan_id != 1){
        $variantValue = false;
    }
}
@endphp
<ul class="nav nav-tabs" id="myTab" role="tablist">
    @if($isBillingActive['billing'])
        <li class="nav-item" role="presentation">
            <button class="nav-link @if($value && $variantValue) active @endif" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">Settings</button>
        </li>
        @if($store->shop_url == "product-table-33.myshopify.com" || $store->shop_url == "merrillsoft.myshopify.com")
        <li class="nav-item" role="presentation">
            <button class="nav-link " id="hide_products-tab" type="button" role="tab" aria-controls="hide_products" aria-selected="false" data-bs-toggle="tab" data-bs-target="#hide_products"  >Hide Products</button>
        </li>
        @endif
    @endif
    <li class="nav-item" role="presentation">
        <button class="nav-link @if(($store->current_charge_id == null) || ($isBillingActive['billing'] == false)) active @endif" id="plan-tab" data-bs-toggle="tab" data-bs-target="#plan" type="button" role="tab" aria-controls="plan" aria-selected="false">Plan</button>
    </li>
    {{-- <li class="nav-item" role="presentation">
        <button class="nav-link" id="support-tab" data-bs-toggle="tab" data-bs-target="#support" type="button" role="tab" aria-controls="support" aria-selected="false">Need Help</button>
    </li> --}}
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="howtouse-tab" data-bs-toggle="tab" data-bs-target="#howtouse" type="button" role="tab" aria-controls="howtouse" aria-selected="false">How to Use</button>
    </li>
</ul>
