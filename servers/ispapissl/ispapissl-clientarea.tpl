
 <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
    <input type="hidden" name="id" value="{$ispapissl.id}" />
    <input type="submit" class="btn btn-primary" name="sslresendcertapproveremail" value="Resend Approveremail"/><br>

    {if $ispapissl.sslresendcertapproveremail}
        <p><b>{$LANG.sslcertapproveremail}</b></p>
        <p>{$LANG.sslcertapproveremaildetails}</p>

        <label> Please enter your approver email here:</label>
        <input type="text" name="approveremail"/><br>
        <label> Or </label>
        <p>
            {foreach from=$ispapissl.approveremails item=approveremail key=num}
                <input type="radio" name="approveremail" value="{$approveremail}"{if $num eq 0} checked{/if} />
                {$approveremail}<br />
            {/foreach}
        </p>

        <table align="center">
            <tr>
                <td><input type="submit" value="{$LANG.clientareabacklink}" class="button" /></td>
                <td><input type="submit" name="sslresendcertapproveremail" value="{$LANG.ordercontinuebutton}" class="button" /></td>
            </tr>
        </table>
</form>
    {else}
        {if $ispapissl.successmessage}
                <div class='infobox'><br>Approver email has been sent successfully.</div><br>
        {/if}
        {if $ispapissl.errormessage}<div class="errorbox">{$ispapissl.errormessage}</div><br />{/if}

        {if $ispapissl.status}
            <h2>{$LANG.sslcertinfo}</h2>

            <table cellspacing="1" cellpadding="0" class="frame"><tr><td>
            <table width="100%" cellpadding="2">
            <tr><td width="200" class="fieldarea">{$LANG.sslstatus}:</td><td><table cellpadding="0" cellspacing="0"><tr>
            <td><b>{$ispapissl.status}</b></td>

            {if ($ispapissl.status eq "Incomplete") || ($ispapissl.status eq "Awaiting Configuration")}
                <td><form method="post" action="{$systemsslurl}configuressl.php?cert={$ispapissl.md5certid}">
                {foreach from=$ispapissl.config key=configdataname item=configdatavalue}
                <input type="hidden" name='{$configdataname}' value='{$configdatavalue}' />
                {/foreach}
                &nbsp;
                <input type="submit" value="{$LANG.sslconfsslcertificate}" />
                </form></td>
            {/if}

            {if $ispapissl.processingstatus eq "PENDING"}
                <td><form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
                <input type="hidden" name="id" value="{$ispapissl.id}" />
                &nbsp;
                <input type="submit" name="sslresendcertapproveremail" value="{$LANG.sslresendcertapproveremail}" />
                </form></td>
            {/if}

            </tr></table></td></tr>

            {if $ispapissl.processingstatus}
                <tr><td class="fieldarea">{$LANG.sslprocessingstatus}:</td><td><b>{$ispapissl.processingstatus}{if $ispapissl.processingdetails} / {$ispapissl.processingdetails}{/if}</b></td></tr>
            {/if}

            {foreach from=$ispapissl.displaydata key=displaydataname item=displaydatavalue}
                <tr><td class="fieldarea">{$displaydataname}:</td><td><b>{$displaydatavalue}</b></td></tr>
            {/foreach}
            </table>
            </td></tr></table>

            {if $ispapissl.crt}
                <p>{$LANG.sslcrt}:</p>
                <table cellspacing="1" cellpadding="0" class="frame"><tr><td>
                <table width="100%" cellpadding="2">
                <tr><td><pre>{$ispapissl.crt}</pre></td></tr>
                </table></td></tr></table>
            {/if}

            {if $ispapissl.cacrt}
                <p>{$LANG.sslcacrt}:</p>
                <table cellspacing="1" cellpadding="0" class="frame"><tr><td>
                <table width="100%" cellpadding="2">
                <tr><td><pre>{$ispapissl.cacrt}</pre></td></tr>
                </table></td></tr></table>
            {/if}

            {if $ispapissl.config.csr}
                <p>{$LANG.sslcsr}:</p>
                <table cellspacing="1" cellpadding="0" class="frame"><tr><td>
                <table width="100%" cellpadding="2">
                <tr><td><pre>{$ispapissl.config.csr}</pre></td></tr>
                </table></td></tr></table>
            {/if}
        {/if}
    {/if}


  


