{extends file="layout.tpl"}

{block name="content"}
    <h2>{AdminLang::trans('setup.tldImport', ['TLD' => 'SSL'])}</h2>

    <div class="tld-import-step top-margin-10">

        <form action="addonmodules.php?module=cnicssl_addon" id="frmTldImport" class="form-horizontal" method="POST">
            <div class="admin-tabs-v2">
                <div class="form-group">
                    <label for="inputMarginType" class="col-md-4 col-sm-6 control-label">
                        {AdminLang::trans('domains.tldImport.marginType')}<br>
                        <small>{AdminLang::trans('domains.tldImport.fixedOrPercentage')}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <select id="inputMarginType" name="MarginType" class="form-control select-inline">
                            <option value="percentage"{if $smarty.post.MarginType eq 'percentage'} selected{/if}>{AdminLang::trans('promos.percentage')}</option>
                            <option value="fixed"{if $smarty.post.MarginType eq 'fixed'} selected{/if}>{AdminLang::trans('promos.fixedamount')}</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputMarginPercent" class="col-md-4 col-sm-6 control-label">
                        {AdminLang::trans('domains.tldImport.profitMargin')}<br>
                        <small>{AdminLang::trans('domains.tldImport.profitMarginDescription')}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <div class="tld-import-percentage-margin{if $smarty.post.MarginType eq 'fixed'} hidden{/if}">
                            <div class="input-group">
                                <input id="inputMarginPercent" name="MarginPercent" type="number" class="form-control" value="{$smarty.post.MarginPercent|default:20}">
                                <span class="input-group-addon hidden-sm">%</span>
                            </div>
                        </div>
                        <div class="tld-import-fixed-margin{if $smarty.post.MarginType neq 'fixed'} hidden{/if}">
                            <div class="input-group">
                                <input id="inputMarginFixed" name="MarginFixed" type="number" class="form-control" value="{$smarty.post.MarginFixed|default:20}" step="0.01">
                                <span class="input-group-addon hidden-sm">{$currency}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputRoundingValue" class="col-md-4 col-sm-6 control-label">
                        {AdminLang::trans('domains.tldImport.rounding')}<br>
                        <small>{AdminLang::trans('domains.tldImport.roundingDescription')}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <select id="inputRoundingValue" name="Rounding" class="form-control select-inline">
                            <option value="-1"{if $smarty.post.Rounding eq ''} selected{/if}>{AdminLang::trans('domains.tldImport.noRounding')}</option>
                            <option value="0.00"{if $smarty.post.Rounding eq '0.00'} selected{/if}>x.00</option>
                            <option value="0.09"{if $smarty.post.Rounding eq '0.09'} selected{/if}>x.09</option>
                            <option value="0.19"{if $smarty.post.Rounding eq '0.19'} selected{/if}>x.19</option>
                            <option value="0.29"{if $smarty.post.Rounding eq '0.29'} selected{/if}>x.29</option>
                            <option value="0.39"{if $smarty.post.Rounding eq '0.39'} selected{/if}>x.39</option>
                            <option value="0.49"{if $smarty.post.Rounding eq '0.49'} selected{/if}>x.49</option>
                            <option value="0.50"{if $smarty.post.Rounding eq '0.50'} selected{/if}>x.50</option>
                            <option value="0.59"{if $smarty.post.Rounding eq '0.59'} selected{/if}>x.59</option>
                            <option value="0.69"{if $smarty.post.Rounding eq '0.69'} selected{/if}>x.69</option>
                            <option value="0.79"{if $smarty.post.Rounding eq '0.79'} selected{/if}>x.79</option>
                            <option value="0.89"{if $smarty.post.Rounding eq '0.89'} selected{/if}>x.89</option>
                            <option value="0.90"{if $smarty.post.Rounding eq '0.90'} selected{/if}>x.90</option>
                            <option value="0.95"{if $smarty.post.Rounding eq '0.95'} selected{/if}>x.95</option>
                            <option value="0.99"{if $smarty.post.Rounding eq '0.99'} selected{/if}>x.99</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputRoundAllCurrencies" class="col-md-4 col-sm-6 control-label">
                        {$lang.roundAllCurrencies}<br>
                        <small>{$lang.roundAllCurrenciesDescription}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <div class="bootstrap-switch bootstrap-switch-wrapper bootstrap-switch-off bootstrap-switch-id-inputSetAutoRegistrar bootstrap-switch-animate">
                            <div class="bootstrap-switch-container">
                                <input id="inputRoundAllCurrencies" type="checkbox" name="RoundAllCurrencies" value="1" data-on-text="{AdminLang::trans('global.yes')}" data-off-text="{AdminLang::trans('global.no')}"{if $smarty.post.RoundAllCurrencies} checked{/if}>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputSetAutoSetup" class="col-md-4 col-sm-6 control-label">
                        {AdminLang::trans('domains.tldImport.setAutoRegistrar')}<br>
                        <small>{AdminLang::trans('domains.tldImport.setAutoRegistrarDescription')}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <select id="inputSetAutoSetup" class="form-control select-inline" name="AutoSetup">
                            <option value=""{if $smarty.post.AutoSetup eq ''} selected{/if}>{AdminLang::trans('products.off')}</option>
                            <option value="order"{if $smarty.post.AutoSetup eq 'order'} selected{/if}>{AdminLang::trans('products.asetupinstantlyafterorder')}</option>
                            <option value="payment"{if !$smarty.post or $smarty.post.AutoSetup eq 'payment'} selected{/if}>{AdminLang::trans('products.asetupafterpay')}</option>
                            <option value="on"{if $smarty.post.AutoSetup eq 'on'} selected{/if}>{AdminLang::trans('products.asetupafteracceptpendingorder')}</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputProductDescriptions" class="col-md-4 col-sm-6 control-label">
                        {$lang.productDescriptions}<br>
                        <small>{$lang.productDescriptionsDescription}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <div class="bootstrap-switch bootstrap-switch-wrapper bootstrap-switch-off bootstrap-switch-id-inputSetAutoRegistrar bootstrap-switch-animate">
                            <div class="bootstrap-switch-container">
                                <input id="inputProductDescriptions" type="checkbox" name="ProductDescriptions" value="1" data-on-text="{AdminLang::trans('global.yes')}" data-off-text="{AdminLang::trans('global.no')}"{if $smarty.post.ProductDescriptions} checked{/if}>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputProductGroups" class="col-md-4 col-sm-6 control-label">
                        {$lang.productGroups}<br>
                        <small>{$lang.productGroupsDescription}</small>
                    </label>
                    <div class="col-md-4 col-sm-6">
                        <div class="bootstrap-switch bootstrap-switch-wrapper bootstrap-switch-off bootstrap-switch-id-inputSetAutoRegistrar bootstrap-switch-animate">
                            <div class="bootstrap-switch-container">
                                <input id="inputProductGroups" type="checkbox" name="ProductGroups" value="1" data-on-text="{AdminLang::trans('global.yes')}" data-off-text="{AdminLang::trans('global.no')}"{if $smarty.post.ProductGroups} checked{/if}>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-12 text-right">
                        <button type="submit" class="btn btn-primary">
                            {AdminLang::trans('global.import')} <span id="importCount">0</span> {$lang.products}
                        </button>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning text-center" role="alert" style="padding: 4px 15px;">
                {AdminLang::trans('domains.tldImport.defaultCurrency', [':currency' => $currency])}
            </div>
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th class="tld-check-all-th">
                        <label>
                            <input type="checkbox" class="check-all-products">
                        </label>
                    </th>
                    <th style="width: 350px;">{$lang.certificate}</th>
                    <th class="tld-import-list text-center"></th>
                    <th class="tld-import-list text-center">{AdminLang::trans('products.existingproduct')}</th>
                    <th class="tld-import-list text-center">{AdminLang::trans('fields.regperiod')}</th>
                    <th class="text-center">
                        <span class="inline-block tld-pricing">
                            <span class="local-pricing">{AdminLang::trans('domains.tldImport.local')}</span>
                            <br>
                            <span class="remote-pricing">{AdminLang::trans('domains.tldImport.cost')}</span>
                        </span>
                        <span class="tld-margin">{AdminLang::trans('domains.tldImport.margin')}</span>
                    </th>
                    <th class="text-center">
                        <span class="inline-block tld-pricing">
                            <span class="local-pricing">{AdminLang::trans('global.new')}</span>
                            <br>
                            <span class="remote-pricing">{AdminLang::trans('domains.tldImport.cost')}</span>
                        </span>
                        <span class="tld-margin">{AdminLang::trans('domains.tldImport.margin')}</span>
                    </th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                {foreach $products as $certificateClass => $product}
                <tr class="product-item" data-cert="{$certificateClass}" data-cost="{$product.Cost}">
                    <td>
                        <input type="checkbox" name="SelectedCertificate[{$certificateClass}]" value="1" class="toggle-switch product-checkbox" id="{$certificateClass}">
                    </td>
                    <td>
                        <label for="{$certificateClass}">
                            {if $product.id}
                                <a href="configproducts.php?action=edit&id={$product.id}">{$product.Provider} {$product.Name}</a>
                            {else}
                                {$product.Provider} {$product.Name}
                            {/if}
                        </label>
                    </td>
                    <td class="text-center">
                        {if $product.AutoSetup}
                            <i class="fas fa-cog text-success" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="{$lang.autoRegistration}"></i>
                        {/if}
                    </td>
                    <td class="text-center">
                        {if $product.id}
                            <i class="fas fa-check text-success"></i>
                        {else}
                            <i class="fas fa-times text-muted"></i>
                        {/if}
                    </td>
                    <td class="text-center">1 {AdminLang::trans('domains.year')}</td>
                    <td class="text-center tld-pricing-td register-pricing">
                        {if $product.id}
                        <span class="tld-pricing inline-block">
                            <span class="current-pricing">{$product.Price|number_format:2}</span><br>
                            <span class="remote-pricing">{$product.Cost}</span>
                        </span>
                        <span class="tld-margin">
                            <span>
                                <span class="inline-block percentage-display" style="display: inline;">{$product.Margin}%</span>
                            </span>
                        </span>
                        {else}
                        <span class="tld-pricing inline-block">
                            <span class="current-pricing">-</span><br>
                            <span class="remote-pricing">-</span>
                        </span>
                        <span class="tld-margin">
                            <span>
                                <span class="inline-block percentage-display" style="display: inline;">-</span>
                            </span>
                        </span>
                        {/if}
                    </td>
                    <td class="text-center tld-pricing-td register-pricing">
                        <span class="tld-pricing inline-block">
                            <input type="hidden" name="NewPrice[{$certificateClass}]" value="{$product.Cost}" />
                            <span class="current-pricing new-pricing" data-cert="{$certificateClass}">{$product.Cost}</span><br>
                            <span class="remote-pricing">{$product.Cost}</span>
                        </span>
                        <span class="tld-margin">
                            <span>
                                <span class="inline-block percentage-display new-margin" data-cert="{$certificateClass}" style="display: inline;">0%</span>
                            </span>
                        </span>
                    </td>
                    <td class="text-center pricing-button">
                        {if $product.id}
                        <a class="btn btn-default btn-sm" href="configproducts.php?action=edit&id={$product.id}#tab=2" target="_blank">
                            {AdminLang::trans('global.pricing')}
                        </a>
                        {/if}
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery('#inputRoundAllCurrencies').bootstrapSwitch();
            jQuery('#inputProductDescriptions').bootstrapSwitch();
            jQuery('#inputProductGroups').bootstrapSwitch();
            countProducts();
            calculatePrices();
            jQuery(document).on('click', '.check-all-products', function () {
                let checked = this.checked;
                jQuery('input.product-checkbox').each(function () {
                    jQuery(this).prop('checked', checked);
                    jQuery(this).trigger('change');
                });
            });
            jQuery(document).on('change', '.product-checkbox', function() {
                countProducts();
            });
            jQuery(document).on('change', '#inputMarginType', function () {
                jQuery('.tld-import-percentage-margin,.tld-import-fixed-margin').toggleClass('hidden');
                calculatePrices();
            });
            jQuery(document).on('change', '#inputMarginPercent', function() {
                calculatePrices();
            });
            jQuery(document).on('change', '#inputMarginFixed', function() {
                calculatePrices();
            });
            jQuery(document).on('change', '#inputRoundingValue', function() {
                calculatePrices();
            });
            function countProducts() {
                jQuery('#importCount').text(jQuery('input[type="checkbox"].product-checkbox:checked').length);
            }
            function calculatePrices() {
                let marginType = jQuery('#inputMarginType').val();
                let profit = Number(jQuery((marginType === 'fixed') ? '#inputMarginFixed' : '#inputMarginPercent').val());
                let roundTo = Number(jQuery('#inputRoundingValue').val());
                jQuery('tr.product-item').each(function () {
                    let cert = jQuery(this).data('cert');
                    let cost = Number(jQuery(this).data('cost'));
                    let newPrice;
                    if (marginType === 'fixed') {
                        newPrice = cost + profit;
                    } else {
                        newPrice = cost * (profit / 100 + 1);
                    }
                    if (roundTo >= 0) {
                        let whole = Math.floor(newPrice);
                        let fraction = newPrice - whole;
                        newPrice = whole + roundTo;
                        if (fraction > roundTo) {
                            newPrice += 1;
                        }
                    }
                    newPrice = Number(newPrice).toFixed(2);
                    let newMargin = Number((newPrice - cost) / cost * 100).toFixed(2);
                    jQuery("input[name='NewPrice["+cert+"]'").val(newPrice);
                    jQuery("span.new-pricing[data-cert='"+cert+"']").html(newPrice);
                    jQuery("span.new-margin[data-cert='"+cert+"']").html(newMargin + '%');
                });
            }
        });
    </script>
{/block}
