{if $error}
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-circle"></i>
        {$error}
    </div>
{/if}

<h2>Load SSL certificates</h2>

<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    <input type="hidden" name="SelectedProductGroup" value="{$selected_product_group}">

    <div class="row">
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="form-group">
                <label for="selectedproductgroup">Select Product Group</label>
                <select class="form-control" name="selectedproductgroup" id="selectedproductgroup" value={$selected_product_group}>
                    <option></option>
                    {foreach $product_groups as $name}
                        <option>{$name}</option>
                    {/foreach}
                </select>
            </div>
        </div>
    </div>

    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i>
        Products will be generated in the selected product group
    </div>

    <button type="submit" class="btn btn-primary" name="loadcertificates">
        <i class="fas fa-upload"></i>
        Load
    </button>
</form>
