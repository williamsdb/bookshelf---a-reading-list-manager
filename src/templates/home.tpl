{include file="header.tpl"}

    <h3>My Bookshelf</h3>

    <div class="container my-3">
        <form method="get" action="">
            <div class="row">
                <div class="col-auto">
                    <select id="listSelect" name="list_id" class="form-select">
                        {foreach from=$lists item=list}
                            <option value="{$list.id}"{if $list.id == $defaultListId} selected{/if}>{$list.name}</option>
                        {/foreach}
                        <option value="0"{if $defaultListId == 0} selected{/if}>Currently Reading</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

	<div class="container">
        {if $books|@count > 0}
            <div class="row">
                {assign var="count" value=0}
                {section name=all loop=$books}
                    <div class="col-12 col-md-6 col-lg-4 mb-4 d-flex align-items-stretch">
                        <div class="card" style="width: 100%;">
                            <div class="card-body" onclick="window.location.href='/viewDetails/{$books[all].id}'" style="cursor:pointer;">
                                <h5 class="card-title">{$books[all].title}</h5>
                                <h6 class="card-subtitle mb-2 text-body-secondary">{$books[all].author}</h6>
                            </div>
                            <div class="card-footer {if $books[all].activityColour|isset && $books[all].activityColour != 'default'} footer-{$books[all].activityColour}{/if}">
                                {if $books[all].read == 0}
                                    <button type="button" class="btn btn-primary" id="readButton" data-bookshelf="{$books[all].id}" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;">Mark as Reading</button>
                                {elseif $books[all].read == 1}
                                    <button type="button" class="btn btn-primary" id="readButton" data-bookshelf="{$books[all].id}" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;">Mark as Read</button>
                                {elseif $books[all].read == 2}
                                    <button type="button" class="btn btn-primary" id="readButton" data-bookshelf="{$books[all].id}" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;">Mark as Unread</button>
                                {/if}
                            {if $defaultListId <> 0}<button type="button" class="btn btn-danger" id="removeButton"  onclick="window.location.href='/removeFromList?bookId={$books[all].id}&listId={$defaultListId}'" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;">Remove from list</button>{/if}
                            </div>
                        </div>
                    </div>
                {/section}
                </div>
        {else}
            <div class="col-12">
                <p class="text-center">No books found.</p>
            </div>
        {/if}
    </div>

{include file="footer.tpl"}