<link rel="stylesheet" href="../modules/addons/ispapissl_addon/ispapissl_addon.css">
<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    {if isset($smarty.post.import)}
      <div class='infobox'><strong><span class='title'>Update successful!</span></strong><br>Your products list has been updated successfully.</div><br>
    {/if}

    <h2>Bulk Price update</h2>
    <span style="font-weight:bold;">Profit Margin: </span><input type='number' step=0.01 placeholder='%'  id='ProfitMargin' name=profitmargin min=0 value={$smarty.post.profitmargin}>
    <input type=submit name=addprofitmargin class="btn btn-primary" value=add>
    <input type="hidden" name="SelectedProductGroup" value="{$smarty.session.selectedproductgroup}">

    <br><br>
    <table class="tableClass">
        <tr>
            <th><span><input type=checkbox onchange=checkAll(this) class=checkall /></span></th>
            <th>SSL Certificate</th>
            <th colspan=2>Price</th>
            <th colspan=2>Currency</th>
        </tr>

        <tr>
            <th></th>
            <th></th>
            <th style=width:16%>Cost</th>
            <th style=width:16%>Sale</th>
            <th style=width:16%>Cost</th>
            <th style=width:16%>Sale</th>
        </tr>
        {counter start=-1 skip=1 print=FALSE}
        {foreach $certificates_and_prices as $certificate => $price_defaultcurrency}
            <tr id="row">
                {if in_array($certificate, $smarty.post.checkboxcertificate)}
                    <td><input type=checkbox class=tocheck  name=checkboxcertificate[] value="{$certificate}" checked="checked"></input></td>
                {else}
                    <td><input type=checkbox class=tocheck  name=checkboxcertificate[] value="{$certificate}"></input></td>
                {/if}
                <td>{$certificate}</td>
                <td name=Myprices value={$price_defaultcurrency['Price']}>{$price_defaultcurrency['Price']}</td>
                <td><input class="sale1" type=text name="{$certificate}_saleprice" id={$certificate}_saleprice value={(($smarty.post.profitmargin/100)*$price_defaultcurrency['Price'])+$price_defaultcurrency['Price']}></input></td>
                <td>{$price_defaultcurrency['Defaultcurrency']}</td>
                <td>
                    {assign var="selectedvalue" value="{$smarty.post.currency[{counter}]}"}
                    <select name=currency[]>
                        {foreach $configured_currencies_in_whmcs as $id=>$code}
                            <option {if $selectedvalue==$id}selected="selected"{/if} value={$id}>{$code}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
        {/foreach}

    </table>
    <br>
    <input type="submit" name="import" class="btn btn-primary" value="Import"/>
</form>

<script type="text/javascript">
  function checkAll(ele) {
    var checkboxes = document.getElementsByTagName("input");
    if (ele.checked) {
      for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type == "checkbox") {
          checkboxes[i].checked = true;
        }
      }
    }
    else {
      for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type == "checkbox") {
          checkboxes[i].checked = false;
        }
     }
  }
}
</script>
