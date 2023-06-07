<div class="tab-pane fade " id="hide_products" role="tabpanel" aria-labelledby="hide_products-tab">
    <div class="container p-5">
        <form action="/new_setting_save" method="POST">
            <div class="row">
                <div class="col-md-4">
                    <label for="product_tag_enable">Enable Product Tag:</label><br>
                    No <label class="switch"><input type="checkbox" @if($settings) @if(!empty($settings->product_tag_enable) && $settings->product_tag_enable == 1) checked @endif  @else checked @endif name="product_tag_enable"  value="1"><span class="slider round" id="product_tag_enable"></span></label> Yes
                </div>
                <div class="col-md-4">
                    <input type="hidden" name="shop" value="{{ $store->shop_url }}">
                    <button type="submit" class="btn btn-success">Update Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>
