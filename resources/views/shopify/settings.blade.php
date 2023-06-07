@php
$value = true;

$variantValue = true;
if (isset($_GET['page'])){
    if ($_GET['page'] == 'VariantTable' && $store->plan_id != 1){
        $variantValue = false;
    }
}
@endphp
@if($isBillingActive['billing'])
    <div class="tab-pane fade @if($value && $variantValue) show active @endif" id="settings" role="tabpanel" aria-labelledby="settings-tab">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    

                    
                    <form action="/save_setting" method="POST">        
                        <h5>Change Columns Labels</h5>
                        <input type="hidden" name="shop" value="{{ $store->shop_url }}">
                        <input type="hidden" name="host" value="{{ $host }}">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="name">Name:</label>
                                <input type="text" class="form-control " id="name" @if($settings) value="{{ !empty($settings->name) ? $settings->name : '' }}" @endif placeholder="Product Name" name="name" required>
                            </div>
                           
                        </div>
                        {{-- <div class="row">
                            <div class="col-md-4">
                                <label for="table_styling_enable">Enable Custom Table Sizing:</label><br>
                                No <label class="switch"><input type="checkbox" @if($settings) @if(!empty($settings->table_styling_enable)) checked @endif  @else checked @endif name="table_styling_enable"  value="1"><span class="slider round" id="table_styling_enable"></span></label> Yes
                            </div>
                            <div class="col-md-4 table_styling" style="@if($settings) @if(!empty($settings->table_styling_enable)) @else display:none @endif @endif">
                                <label for="table_width">Width:</label>
                                <input type="number" min="50"  val class="form-control" id="table_width" @if($settings) value="{{ !empty($settings->table_width) ? $settings->table_width : '50' }}" @endif placeholder="Width" name="table_width">
                            </div>
                            <div class="col-md-4 table_styling" style="@if($settings) @if(!empty($settings->table_styling_enable)) @else display:none @endif @endif">
                                <label for="table_height">Height:</label>
                                <input type="number" min="50"  val class="form-control" id="table_height" @if($settings) value="{{ !empty($settings->table_height) ? $settings->table_height : '50' }}" @endif placeholder="Height" name="table_height">
                            </div>
                        </div> --}}
                       
                        <button type="submit" class="btn btn-success">Update Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
