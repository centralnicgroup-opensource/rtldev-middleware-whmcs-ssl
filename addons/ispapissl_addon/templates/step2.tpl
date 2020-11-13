{extends file="layout.tpl"}

{block name="content"}

<h2>Bulk Pricing update</h2>

<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    <input type="hidden" name="SelectedProductGroup" value="{$smarty.session.selectedproductgroup}">

    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-12">
            <label for="ProfitMargin">Profit Margin</label>
            <div class="input-group">
                <input class="form-control" type="number" step=0.01 id="ProfitMargin" name="ProfitMargin" min="0" value="{$smarty.post.ProfitMargin|default:0}" />
                <span class="input-group-addon" id="basic-addon2">
                    <i class="fas fa-percent"></i>
                </span>
                <span class="input-group-btn">
                    <button type="submit" name="AddProfitMargin" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Add
                    </button>
                </span>
            </div>
        </div>
    </div>

    <br /><br />
    <table class="datatable" width="100%" cellspacing="1" cellpadding="3" border="0">
        <tr>
            <th>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" onchange="checkAll(this)" />
                    </label>
                </div>
            </th>
            <th>SSL Certificate</th>
            <th style="width:32%">Cost (yearly)</th>
            <th style="width:32%">Sale</th>
        </tr>
        {counter start = -1 skip = 1 print = false}
        {foreach $products as $certificateClass => $product}
            <tr>
                <td>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="SelectedCertificate[{$certificateClass}]" {if isset($smarty.post.SelectedCertificate[$certificateClass])}checked {/if}/>
                        </label>
                    </div>
                </td>
                <td>{$product.Name}</td>
                <td>{$product.Price} {$currency}</td>
                <td>
                    <label>
                        <input class="form-control" type="text" name="SalePrice[{$certificateClass}]" value="{$product.NewPrice}" />
                    </label>
                </td>
            </tr>
        {/foreach}
    </table>
    <br />
    <input type="hidden" name="Currency" value="{$currency}" />
    <button type="submit" name="import" class="btn btn-primary">
        <i class="fas fa-upload"></i>
        Import
    </button>
</form>

<script type="text/javascript">
function checkAll(element) {
    const checkboxes = document.getElementsByTagName("input");
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type === "checkbox") {
            checkboxes[i].checked = element.checked;
        }
    }
}
</script>

{/block}
