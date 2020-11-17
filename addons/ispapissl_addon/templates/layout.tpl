<div class="pull-right" style="margin-top: -42px;">
    <a href="https://www.hexonet.net/" target="_blank">
        <img src="{$logo}" title="Powered by HEXONET" alt="HEXONET" />
    </a>
</div>

{if $error}
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-circle"></i>
        <strong>Error!</strong> {$error}
    </div>
{elseif $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>Success!</strong> {$success}
    </div>
{/if}

{block name="content"}{/block}
