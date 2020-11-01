{if isset($smarty.post.import)}
    {if !isset($smarty.post.SelectedCertificate)}
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Error!</strong> Please select an SSL Certificate.
        </div>
    {/if}
{/if}

<h2>Bulk Pricing update</h2>

<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    <input type="hidden" name="SelectedProductGroup" value="{$smarty.session.selectedproductgroup}">

    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-12">
            <label for="RegistrationPeriod">Registration Period</label>
            <div class="input-group">
                <select class="form-control" id="RegistrationPeriod" name="RegistrationPeriod">
                    <option value="1" {if $smarty.post.RegistrationPeriod == 1}selected{/if}>1Y</option>
                    <option value="2" {if $smarty.post.RegistrationPeriod == 2}selected{/if}>2Y</option>
                </select>
                <span class="input-group-btn">
                    <button type="submit" name="calculateregprice" class="btn btn-primary">
                        <i class="fas fa-calculator"></i>
                        Calculate
                    </button>
                </span>
            </div>
        </div>
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
            <th colspan="2">Price</th>
            <th colspan="2">Currency</th>
        </tr>
        <tr>
            <th></th>
            <th></th>
            <th style="width:16%">Cost ({if $smarty.post.RegistrationPeriod}{$smarty.post.RegistrationPeriod}Y{else}1Y{/if})</th>
            <th style="width:16%">Sale</th>
            <th style="width:16%">Cost</th>
            <th style="width:16%">Sale</th>
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
                <td>{$product.Price}</td>
                <td>
                    <label>
                        <input class="form-control" type="text" name="SalePrice[{$certificateClass}]" value="{$product.NewPrice}" />
                    </label>
                </td>
                <td>{$product.DefaultCurrency}</td>
                <td>
                    {assign var="selectedvalue" value="{$smarty.post.currency[{counter}]}"}
                    <label>
                        <select class="form-control" name="Currency[{$certificateClass}]">
                            {foreach $configured_currencies_in_whmcs as $id => $code}
                                <option {if $selectedvalue == $id}selected{/if} value="{$id}">{$code}</option>
                            {/foreach}
                        </select>
                    </label>
                </td>
            </tr>
        {/foreach}
    </table>
    <br />
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
