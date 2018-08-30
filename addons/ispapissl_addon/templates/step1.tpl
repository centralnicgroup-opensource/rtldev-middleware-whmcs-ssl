<link rel="stylesheet" href="../modules/addons/ispapissl_addon/ispapissl_addon.css">

    <label><h2>Please load SSL certificates</h2></label><br>
    <form action="addonmodules.php?module=ispapissl_addon" method="POST">
        <input type="hidden" name="SelectedProductGroup" value="{$selected_product_group}">
        Select Product Group
        <select name="selectedproductgroup" value={$selected_product_group}>
            <option></option>
            {foreach $product_groups as $value}
                <option>{$value['name']}</option>
            {/foreach}
        </select>
        <br><br>
        <input type="submit" class="btn btn-primary" name="loadcertificates" value="Load"/><br>

    </form>
