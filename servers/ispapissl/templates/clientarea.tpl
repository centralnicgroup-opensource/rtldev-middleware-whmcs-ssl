{if $successMessage}
    <br />
    <div class='alert alert-success text-center'>{$successMessage}</div>
{/if}

<div class="text-left">
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
                                    <td>
                                        <b>{$orderStatus}</b>
                                    </td>
                                    {if in_array($orderStatus, ['Incomplete', 'Awaiting Configuration'])}
                                    <td>
                                        <form method="post" action="{$systemsslurl}configuressl.php?cert={$md5certId}">
                                        {foreach from=$config key=configName item=configValue}
                                            <input type="hidden" name='{$configName}' value='{$configValue}' />
                                        {/foreach}
                                            &nbsp;<button type="submit" class="btn btn-primary">{$LANG.sslconfsslcertificate}</button>
                                        </form>
                                    </td>
                                    {/if}
                                    {if in_array($cert.status, ['REQUESTED', 'REQUESTEDCREATE', 'REQUESTEDRENEW', 'REQUESTEDREISSUE', 'PENDING', 'PENDINGCREATE', 'PENDINGRENEW', 'PENDINGREISSUE'])}
                                    <td>
                                        <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
                                            <input type="hidden" name="id" value="{$id}" />
                                            &nbsp;<button type="submit" class="btn btn-info" name="sslresendcertapproveremail">{$LANG.sslresendcertapproveremail}</button>
                                        </form>
                                    </td>
                                    {/if}
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {if $cert}
                    <tr>
                        <td class="fieldarea">{$LANG.sslprocessingstatus}:</td>
                        <td><b>{$cert.status}{if $cert.statusdetails} ({$cert.statusdetails}){/if}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">CN:</td>
                        <td><b>{$cert.cn}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Validation email:</td>
                        <td><b><a href="mailto:{$cert.validationemail}">{$cert.validationemail}</a></b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Created:</td>
                        <td><b>{$cert.createddate}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Updated:</td>
                        <td><b>{$cert.updateddate}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Expiration:</td>
                        <td><b>{$cert.registrationexpirationdate}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Order ID:</td>
                        <td><b>{$cert.orderid}</b></td>
                    </tr>
                    <tr>
                        <td class="fieldarea">Vendor Order ID:</td>
                        <td><b>{$cert.supplierorderid}</b></td>
                    </tr>
                    {/if}
                </table>
            </td>
        </tr>
    </table>

    {if $cert}
    <br />
    <ul class="nav nav-tabs" id="contactTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="owner-tab" data-toggle="tab" href="#owner" role="tab" aria-controls="crt" aria-selected="true">
                Owner
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab" aria-controls="ca" aria-selected="false">
                Admin Contact
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="tech-tab" data-toggle="tab" href="#tech" role="tab" aria-controls="csr" aria-selected="false">
                Tech Contact
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="billing-tab" data-toggle="tab" href="#billing" role="tab" aria-controls="csr" aria-selected="false"}>
                Billing Contact
            </a>
        </li>
    </ul>
    <div class="tab-content" id="contactTabContent">
        <div class="tab-pane fade show active mt-2" id="owner" role="tabpanel" aria-labelledby="owner-tab">
            <table width="100%" cellpadding="2">
                <tr>
                    <td class="fieldarea" width="200">Name</td>
                    <td><b>{$cert.name}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Title</td>
                    <td><b>{$cert.jobtitle}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Organization</td>
                    <td><b>{$cert.organization}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">e-mail</td>
                    <td><b><a href="mailto:{$cert.email}">{$cert.email}</a></b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Phone</td>
                    <td><b>{$cert.phone}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Address</td>
                    <td><b>{$cert.street}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">ZIP</td>
                    <td><b>{$cert.zip}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">City</td>
                    <td><b>{$cert.city}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Province</td>
                    <td><b>{$cert.province}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Country</td>
                    <td><b>{$cert.country}</b></td>
                </tr>
            </table>
        </div>
        <div class="tab-pane fade mt-2" id="admin" role="tabpanel" aria-labelledby="admin-tab">
            <table width="100%" cellpadding="2">
                <tr>
                    <td class="fieldarea" width="200">Name</td>
                    <td><b>{$cert.admincontactname}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Title</td>
                    <td><b>{$cert.admincontactjobtitle}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Organization</td>
                    <td><b>{$cert.admincontactorganization}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">e-mail</td>
                    <td><b><a href="mailto:{$cert.admincontactemail}">{$cert.admincontactemail}</a></b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Phone</td>
                    <td><b>{$cert.admincontactphone}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Address</td>
                    <td><b>{$cert.admincontactstreet}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">ZIP</td>
                    <td><b>{$cert.admincontactzip}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">City</td>
                    <td><b>{$cert.admincontactcity}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Province</td>
                    <td><b>{$cert.admincontactprovince}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Country</td>
                    <td><b>{$cert.admincontactcountry}</b></td>
                </tr>
            </table>
        </div>
        <div class="tab-pane fade mt-2" id="tech" role="tabpanel" aria-labelledby="tech-tab">
            <table width="100%" cellpadding="2">
                <tr>
                    <td class="fieldarea" width="200">Name</td>
                    <td><b>{$cert.techcontactname}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Title</td>
                    <td><b>{$cert.techcontactjobtitle}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Organization</td>
                    <td><b>{$cert.techcontactorganization}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">e-mail</td>
                    <td><b><a href="mailto:{$cert.techcontactemail}">{$cert.techcontactemail}</a></b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Phone</td>
                    <td><b>{$cert.techcontactphone}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Address</td>
                    <td><b>{$cert.techcontactstreet}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">ZIP</td>
                    <td><b>{$cert.techcontactzip}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">City</td>
                    <td><b>{$cert.techcontactcity}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Province</td>
                    <td><b>{$cert.techcontactprovince}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Country</td>
                    <td><b>{$cert.techcontactcountry}</b></td>
                </tr>
            </table>
        </div>
        <div class="tab-pane fade mt-2" id="billing" role="tabpanel" aria-labelledby="billing-tab">
            <table width="100%" cellpadding="2">
                <tr>
                    <td class="fieldarea" width="200">Name</td>
                    <td><b>{$cert.billingcontactname}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Title</td>
                    <td><b>{$cert.billingcontactjobtitle}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Organization</td>
                    <td><b>{$cert.billingcontactorganization}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">e-mail</td>
                    <td><b><a href="mailto:{$cert.billingcontactemail}">{$cert.billingcontactemail}</a></b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Phone</td>
                    <td><b>{$cert.billingcontactphone}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Address</td>
                    <td><b>{$cert.billingcontactstreet}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">ZIP</td>
                    <td><b>{$cert.billingcontactzip}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">City</td>
                    <td><b>{$cert.billingcontactcity}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Province</td>
                    <td><b>{$cert.billingcontactprovince}</b></td>
                </tr>
                <tr>
                    <td class="fieldarea">Country</td>
                    <td><b>{$cert.billingcontactcountry}</b></td>
                </tr>
            </table>
        </div>
    </div>

    <br />
    <ul class="nav nav-tabs" id="certTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="csr-tab" data-toggle="tab" href="#csr" role="tab" aria-controls="csr" aria-selected="true"{if !$cert.csr} disabled{/if}>
                {$LANG.sslcsr}
            </a>
        </li>
        {if !in_array($cert.status, ['REQUESTED', 'REQUESTEDCREATE', 'PENDING', 'PENDINGCREATE'])}
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="crt-tab" data-toggle="tab" href="#crt" role="tab" aria-controls="crt" aria-selected="false"{if !$cert.crt} disabled{/if}>
                {$LANG.sslcrt}
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="ca-tab" data-toggle="tab" href="#ca" role="tab" aria-controls="ca" aria-selected="false"{if !$cert.cacrt} disabled{/if}>
                {$LANG.sslcacrt}
            </a>
        </li>
        {/if}
    </ul>
    <div class="tab-content" id="certTabContent">
        <div class="tab-pane fade show active mt-2" id="csr" role="tabpanel" aria-labelledby="csr-tab">
            <pre>{$cert.csr}</pre>
        </div>
        {if !in_array($cert.status, ['REQUESTED', 'REQUESTEDCREATE', 'PENDING', 'PENDINGCREATE'])}
        <div class="tab-pane fade mt-2" id="crt" role="tabpanel" aria-labelledby="crt-tab">
            <pre>{$cert.crt}</pre>
        </div>
        <div class="tab-pane fade mt-2" id="ca" role="tabpanel" aria-labelledby="ca-tab">
            <pre>{$cert.cacrt}</pre>
        </div>
        {/if}
    </div>
    {/if}

</div>
