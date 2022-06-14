{if $successMessage}
    <br />
    <div class='alert alert-success text-center'>{$successMessage}</div>
{/if}

<div align="left">
    <div class="row py-2">
        <div class="col-md-4">{$LANG.sslstatus}</div>
        <div class="col-md-8">
            {$orderStatus}
            {if in_array($orderStatus, ['Incomplete', 'Awaiting Configuration'])}
                <br /><br />
                <form method="post" action="{$systemsslurl}configuressl.php?cert={$md5certId}">
                    {foreach from=$config key=configName item=configValue}
                        <input type="hidden" name='{$configName}' value='{$configValue}' />
                    {/foreach}
                    &nbsp;<button type="submit" class="btn btn-primary">{$LANG.sslconfsslcertificate}</button>
                </form>
            {/if}
        </div>
    </div>
    {if $cert}
        <div class="row py-2">
            <div class="col-md-4">{$LANG.sslprocessingstatus}</div>
            <div class="col-md-8">{$cert.status}{if $cert.statusdetails} ({$cert.statusdetails}){/if}</div>
        </div>
        <div class="row py-2">
            <div class="col-md-4">CN</div>
            <div class="col-md-8">{$cert.sslcertcn}</div>
        </div>
        {if $logo}
            <div class="row py-2">
                <div class="col-md-4">Provider</div>
                <div class="col-md-8"><img src="{$webRoot}/modules/addons/cnicssl_addon/logos/{$logo}" /></div>
            </div>
        {/if}
        <div class="row py-2">
            <div class="col-md-4">{$LANG.webServerType}</div>
            <div class="col-md-8">{$cert.serversoftware}</div>
        </div>
        <div class="row py-2">
            <div class="col-md-4">{$LANG.created}</div>
            <div class="col-md-8">{$cert.createddate}</div>
        </div>
        <div class="row py-2">
            <div class="col-md-4">{$LANG.updated}</div>
            <div class="col-md-8">{$cert.updateddate}</div>
        </div>
        {if $cert.registrationexpirationdate}
            <div class="row py-2">
                <div class="col-md-4">{$LANG.expiration}</div>
                <div class="col-md-8">{$cert.registrationexpirationdate}</div>
            </div>
        {/if}
        <div class="row py-2">
            <div class="col-md-4">{$LANG.orderId}</div>
            <div class="col-md-8">{$cert.orderid}</div>
        </div>
        {if $cert.supplierorderid}
            <div class="row py-2">
                <div class="col-md-4">{$LANG.vendorId}</div>
                <div class="col-md-8">{$cert.supplierorderid}</div>
            </div>
        {/if}
        <div class="row py-2 dcv">
            <div class="col-md-4">{$LANG.ssl.dcv}</div>
            <div class="col-md-8">
                <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">{$cert.validation}</span>
                        </div>
                        <div class="input-group-append">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dcv-dropdown" data-toggle="dropdown" aria-expanded="false">
                                Change
                            </button>
                            <div class="dropdown-menu" aria-labelledby="dcv-dropdown">
                                {if $cert.validation neq "EMAIL"}<button class="dropdown-item" name="validate-email" type="submit">EMAIL</button>{/if}
                                {if $cert.validation neq "URL" and $cert.validation neq "FILE"}<button class="dropdown-item" name="validate-file" type="submit">FILE</button>{/if}
                                {if $cert.validation neq "DNSZONE" and $cert.validation neq "DNS"}<button class="dropdown-item" name="validate-dns" type="submit">DNS</button>{/if}
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        {if $cert.validation == "EMAIL"}
            <div class="row py-2">
                <div class="col-md-4">email</div>
                <div class="col-md-8">
                    <a href="mailto:{$cert.validationemail}">{$cert.validationemail}</a>
                    {if in_array($cert.status, ['REQUESTED', 'REQUESTEDCREATE', 'REQUESTEDRENEW', 'REQUESTEDREISSUE', 'PENDING', 'PENDINGCREATE', 'PENDINGRENEW', 'PENDINGREISSUE'])}
                        <br /><br />
                        <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
                            <button type="submit" class="btn btn-info" name="sslresendcertapproveremail">{$LANG.sslresendcertapproveremail}</button>
                        </form>
                    {/if}
                </div>
            </div>
        {elseif $cert.validation == "URL" or $cert.validation == "FILE"}
            <div class="row py-2 dcv-property">
                <div class="col-md-4 dcv-field">File name</div>
                <div class="col-md-8 dcv-value">
                    <div class="input-group">
                        <input type="text" class="form-control" id="dcv-file-name" value="{if $cert.validation == "URL"}{$cert.validationurl}{else}{$cert.fileauthname}{/if}" readonly="">
                        <div class="input-group-btn input-group-append">
                            <button type="button" class="btn btn-default copy-to-clipboard" data-clipboard-target="#dcv-file-name">
                                <img src="{$webRoot}/assets/img/clippy.svg" alt="Copy to clipboard" width="15">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row py-2 dcv-property">
                <div class="col-md-4 dcv-field">File contents</div>
                <div class="col-md-8 dcv-value">
                    <div class="input-group">
                        <input type="text" class="form-control" id="dcv-file-contents" value="{if $cert.validation == "URL"}{$cert.validationurlcontent}{else}{$cert.fileauthcontents}{/if}" readonly="">
                        <div class="input-group-btn input-group-append">
                            <button type="button" class="btn btn-default copy-to-clipboard" data-clipboard-target="#dcv-file-contents">
                                <img src="{$webRoot}/assets/img/clippy.svg" alt="Copy to clipboard" width="15">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        {elseif $cert.validation == "DNSZONE" or $cert.validation == "DNS"}
            <div class="row py-2 dcv-property">
                <div class="col-md-4 dcv-field">{$LANG.ssl.type}</div>
                <div class="col-md-8 dcv-value">{$cert.dnstype}</div>
            </div>
            <div class="row py-2 dcv-property">
                <div class="col-md-4 dcv-field">{$LANG.ssl.host}</div>
                <div class="col-md-8 dcv-value">
                    <div class="input-group">
                        <input type="text" class="form-control" id="dcv-dns-host" value="{$cert.dnshost}" readonly="">
                        <div class="input-group-btn input-group-append">
                            <button type="button" class="btn btn-default copy-to-clipboard" data-clipboard-target="#dcv-dns-host">
                                <img src="{$webRoot}/assets/img/clippy.svg" alt="Copy to clipboard" width="15">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row py-2 dcv-property">
                <div class="col-md-4 dcv-field">{$LANG.ssl.value}</div>
                <div class="col-md-8 dcv-value">
                    <div class="input-group">
                        <input type="text" class="form-control" id="dcv-dns-value" value="{$cert.dnsvalue}" readonly="">
                        <div class="input-group-btn input-group-append">
                            <button type="button" class="btn btn-default copy-to-clipboard" data-clipboard-target="#dcv-dns-value">
                                <img src="{$webRoot}/assets/img/clippy.svg" alt="Copy to clipboard" width="15">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <br />
        <ul class="nav nav-tabs" id="contactTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="owner-tab" data-toggle="tab" href="#owner" role="tab" aria-controls="crt" aria-selected="true">
                    {$LANG.owner}
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab" aria-controls="ca" aria-selected="false">
                    {$LANG.adminContact}
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="tech-tab" data-toggle="tab" href="#tech" role="tab" aria-controls="csr" aria-selected="false">
                    {$LANG.techContact}
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="billing-tab" data-toggle="tab" href="#billing" role="tab" aria-controls="csr" aria-selected="false"}>
                    {$LANG.billingContact}
                </a>
            </li>
        </ul>
        <div class="tab-content" id="contactTabContent">
            <div class="tab-pane fade show active mt-2" id="owner" role="tabpanel" aria-labelledby="owner-tab">
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Contact Name']}</div>
                    <div class="col-md-8">{$cert.name}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Job Title']}</div>
                    <div class="col-md-8">{$cert.jobtitle}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Organisation Name']}</div>
                    <div class="col-md-8">{$cert.organization}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Email Address']}</div>
                    <div class="col-md-8"><a href="mailto:{$cert.email}">{$cert.email}</a></div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Phone Number']}</div>
                    <div class="col-md-8">{$cert.phone}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Address']}</div>
                    <div class="col-md-8">{$cert.street}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['ZIP Code']}</div>
                    <div class="col-md-8">{$cert.zip}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['City']}</div>
                    <div class="col-md-8">{$cert.city}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['State']}</div>
                    <div class="col-md-8">{$cert.province}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Country']}</div>
                    <div class="col-md-8">{$cert.country}</div>
                </div>
            </div>
            <div class="tab-pane fade mt-2" id="admin" role="tabpanel" aria-labelledby="admin-tab">
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Contact Name']}</div>
                    <div class="col-md-8">{$cert.admincontactname}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Job Title']}</div>
                    <div class="col-md-8">{$cert.admincontactjobtitle}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Organisation Name']}</div>
                    <div class="col-md-8">{$cert.admincontactorganization}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Email Address']}</div>
                    <div class="col-md-8"><a href="mailto:{$cert.admincontactemail}">{$cert.admincontactemail}</a></div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Phone Number']}</div>
                    <div class="col-md-8">{$cert.admincontactphone}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Address']}</div>
                    <div class="col-md-8">{$cert.admincontactstreet}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['ZIP Code']}</div>
                    <div class="col-md-8">{$cert.admincontactzip}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['City']}</div>
                    <div class="col-md-8">{$cert.admincontactcity}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['State']}</div>
                    <div class="col-md-8">{$cert.admincontactprovince}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Country']}</div>
                    <div class="col-md-8">{$cert.admincontactcountry}</div>
                </div>
            </div>
            <div class="tab-pane fade mt-2" id="tech" role="tabpanel" aria-labelledby="tech-tab">
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Contact Name']}</div>
                    <div class="col-md-8">{$cert.techcontactname}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Job Title']}</div>
                    <div class="col-md-8">{$cert.techcontactjobtitle}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Organisation Name']}</div>
                    <div class="col-md-8">{$cert.techcontactorganization}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Email Address']}</div>
                    <div class="col-md-8"><a href="mailto:{$cert.techcontactemail}">{$cert.techcontactemail}</a></div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Phone Number']}</div>
                    <div class="col-md-8">{$cert.techcontactphone}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Address']}</div>
                    <div class="col-md-8">{$cert.techcontactstreet}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['ZIP Code']}</div>
                    <div class="col-md-8">{$cert.techcontactzip}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['City']}</div>
                    <div class="col-md-8">{$cert.techcontactcity}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['State']}</div>
                    <div class="col-md-8">{$cert.techcontactprovince}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Country']}</div>
                    <div class="col-md-8">{$cert.techcontactcountry}</div>
                </div>
            </div>
            <div class="tab-pane fade mt-2" id="billing" role="tabpanel" aria-labelledby="billing-tab">
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Contact Name']}</div>
                    <div class="col-md-8">{$cert.billingcontactname}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Job Title']}</div>
                    <div class="col-md-8">{$cert.billingcontactjobtitle}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Organisation Name']}</div>
                    <div class="col-md-8">{$cert.billingcontactorganization}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Email Address']}</div>
                    <div class="col-md-8"><a href="mailto:{$cert.billingcontactemail}">{$cert.billingcontactemail}</a></div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Phone Number']}</div>
                    <div class="col-md-8">{$cert.billingcontactphone}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Address']}</div>
                    <div class="col-md-8">{$cert.billingcontactstreet}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['ZIP Code']}</div>
                    <div class="col-md-8">{$cert.billingcontactzip}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['City']}</div>
                    <div class="col-md-8">{$cert.billingcontactcity}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['State']}</div>
                    <div class="col-md-8">{$cert.billingcontactprovince}</div>
                </div>
                <div class="row py-2">
                    <div class="col-md-4">{$LANG.domaincontactdetails['Country']}</div>
                    <div class="col-md-8">{$cert.billingcontactcountry}</div>
                </div>
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
                <textarea rows="15" class="form-control" id="txt-csr" readonly="">{$cert.csr}</textarea>
                <br>
                <button type="button" class="btn btn-default btn-sm copy-to-clipboard pull-right float-right" data-clipboard-target="#txt-csr">
                    <i aria-hidden="true" class="far fa-clipboard-list fa-lg" title="{lang key='copyToClipboard'}"></i>
                    {lang key='copyToClipboard'}
                </button>
                <div class="clearfix"></div>
            </div>
            {if !in_array($cert.status, ['REQUESTED', 'REQUESTEDCREATE', 'PENDING', 'PENDINGCREATE'])}
                <div class="tab-pane fade mt-2" id="crt" role="tabpanel" aria-labelledby="crt-tab">
                    <textarea rows="15" class="form-control" id="txt-crt" readonly="">{$cert.crt}</textarea>
                    <button type="button" class="btn btn-default btn-sm copy-to-clipboard pull-right float-right" data-clipboard-target="#txt-crt">
                        <i aria-hidden="true" class="far fa-clipboard-list fa-lg" title="{lang key='copyToClipboard'}"></i>
                        {lang key='copyToClipboard'}
                    </button>
                    <div class="clearfix"></div>
                </div>
                <div class="tab-pane fade mt-2" id="ca" role="tabpanel" aria-labelledby="ca-tab">
                    <textarea rows="15" class="form-control" id="txt-cacrt" readonly="">{$cert.cacrt}</textarea>
                    <button type="button" class="btn btn-default btn-sm copy-to-clipboard pull-right float-right" data-clipboard-target="#txt-ca">
                        <i aria-hidden="true" class="far fa-clipboard-list fa-lg" title="{lang key='copyToClipboard'}"></i>
                        {lang key='copyToClipboard'}
                    </button>
                    <div class="clearfix"></div>
                </div>
            {/if}
        </div>

        <br>
        <div class="well">
            <h4>{lang key='ssl.installing'}</h4>
            <p>{lang key='ssl.howToInstall'}</p>
        </div>
    {/if}
</div>
