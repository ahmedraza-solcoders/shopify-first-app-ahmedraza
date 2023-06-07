<div class="tab-pane fade" id="support" role="tabpanel" aria-labelledby="support-tab">
    <div class="support_wrapper mt-2">
        <div class="row">
            <form action="/send_support_email">
                <input type="hidden" name="shop" value="{{ $store->shop_url }}">
            <div class="col-md-12">
                <label for="email"><strong>Email</strong></label>
                <input type="email" class="form-control" name="email" id="email" placeholder="Enter your Email" @if($adminEmail) value="{{ $adminEmail }}" @endif required>
                <label>The email you wish us to reply to.</label>
            </div><br>
            <div class="col-md-12">
                <label for="store_password"><strong>Storefront Password (if password protected)</strong></label>
                <input type="text" class="form-control" name="store_password" id="store_password" placeholder="Enter your Storefront Password (if password protected)">
                <label>This is not your admin password! read more about it in <a href="https://help.shopify.com/en/manual/online-store/themes/password-page">Shopify Help</a> .</label>
            </div><br>
            <div class="col-md-12">
                <label for="description"><strong>Description of the issue</strong></label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="6" placeholder="Description of the issue" required></textarea>
                <label>Any additional requests or information.</label>
            </div><br>
            <button type="submit" class="btn btn-primary">Request Help</button>
            </form>
        </div>
    </div>
</div>
