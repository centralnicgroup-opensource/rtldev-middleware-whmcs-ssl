{if $sslresendcertapproveremail}
     <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
        {if $errormessage}
        <div class="alert alert-warning text-center">{$errormessage}</div><br />
        {/if}
        <input type="hidden" name="id" value="{$id}" />
        <input type="submit" class="btn btn-primary" name="sslresendcertapproveremail" value="Resend Approveremail"/><br>

        <p><b>{$LANG.sslcertapproveremail}</b></p>
        <p>{$LANG.sslcertapproveremaildetails}</p>

        <label> Please enter your approver email here:</label>
        <input type="text" name="approveremail"/><br>
        <label> Or </label>
        <p>
            {foreach from=$approveremails item=approveremail key=num}
            <input type="radio" class="radio-button" name="approveremails" value="{$approveremail}"{if $num eq 0} checked{/if} />
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
    {if $successmessage}
        <br><div class='alert alert-success text-center'>Approver email has been resent successfully.</div>
    {/if}

    {if $status}
        <h2>{$LANG.sslcertinfo}</h2>

        <table cellspacing="1" cellpadding="0" class="frame">
            <tr>
                <td>
                    <table width="100%" cellpadding="2">
                        <tr>
                            <td width="200" class="fieldarea">{$LANG.sslstatus}:</td>
                            <td>
                                <table cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td><b>{$status}</b></td>
                                        {if ($status eq "Incomplete") || ($status eq "Awaiting Configuration")}
                                        <td>
                                            <form method="post" action="{$systemsslurl}configuressl.php?cert={$md5certid}">
                                            {foreach from=$config key=configdataname item=configdatavalue}
                                                <input type="hidden" name='{$configdataname}' value='{$configdatavalue}' />
                                            {/foreach}
                                                <input type="submit" value="{$LANG.sslconfsslcertificate}" />
                                            </form>
                                        </td>
                                        {/if}
                                        {if $processingstatus eq "PENDING"}
                                        <td>
                                            <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
                                                <input type="hidden" name="id" value="{$id}" />
                                                <input type="submit" name="sslresendcertapproveremail" value="{$LANG.sslresendcertapproveremail}" />
                                            </form>
                                        </td>
                                        {/if}
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        {if $processingstatus}
                        <tr>
                            <td class="fieldarea">{$LANG.sslprocessingstatus}:</td>
                            <td><b>{$processingstatus}{if $processingdetails} / {$processingdetails}{/if}</b></td>
                        </tr>
                        {/if}

                        {foreach from=$displaydata key=displaydataname item=displaydatavalue}
                        <tr>
                            <td class="fieldarea">{$displaydataname}:</td>
                            <td><b>{$displaydatavalue}</b></td>
                        </tr>
                        {/foreach}
                    </table>
                </td>
            </tr>
        </table>

        {if $crt}
        <p>{$LANG.sslcrt}:</p>
        <table cellspacing="1" cellpadding="0" class="frame">
            <tr>
                <td>
                    <table width="100%" cellpadding="2">
                        <tr>
                            <td><pre>{$crt}</pre></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        {/if}

        {if $cacrt}
        <p>{$LANG.sslcacrt}:</p>
        <table cellspacing="1" cellpadding="0" class="frame">
            <tr>
                <td>
                    <table width="100%" cellpadding="2">
                        <tr>
                            <td><pre>{$cacrt}</pre></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        {/if}

        {if $config.csr}
        <p>{$LANG.sslcsr}:</p>
        <table cellspacing="1" cellpadding="0" class="frame">
            <tr>
                <td>
                    <table width="100%" cellpadding="2">
                        <tr>
                            <td><pre>{$config.csr}</pre></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        {/if}
    {/if}
{/if}
