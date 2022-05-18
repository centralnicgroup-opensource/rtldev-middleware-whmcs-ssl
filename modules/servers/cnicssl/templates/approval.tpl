{if $errorMessage}
    <div class="alert alert-danger text-center">{$errorMessage}</div>
    <br />
{/if}

<div class="text-left">
    <h2>{$LANG.sslcertapproveremail}</h2>
    <p>{$LANG.sslcertapproveremaildetails}</p>

    <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
        <label for="customApproverEmail">{$LANG.sslcertapproveremail}</label>
        <input type="text" name="customApproverEmail" id="customApproverEmail" />
        <br />
        /
        <p>
        {foreach from=$approverEmails item=approverEmail key=num}
            <input type="radio" class="radio-button" name="approverEmail" id="approverEmail{$num}" value="{$approverEmail}"{if $num eq 0} checked{/if} />
            <label for="approverEmail{$num}">{$approverEmail}</label>
            <br />
        {/foreach}
        </p>
        <div class="center">
            <button type="submit" class="btn btn-default">{$LANG.clientareabacklink}</button>
            <button type="submit" name="sslresendcertapproveremail" class="btn btn-primary">{$LANG.ordercontinuebutton}</button>
        </div>
    </form>
</div>
