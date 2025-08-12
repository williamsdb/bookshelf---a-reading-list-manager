{include file="header.tpl"}

<h3>Manage Lists</h3>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>List Name</th>
                <th width="10%">Default</th>
                <th width="10%">Book Count</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$lists item=list}
            <tr>
                <td><a href="/editList?id={$list.id}">{$list.name}</a></td>
                <td>{if $list.default}Yes{else}No{/if}</td>
                <td align="right">{$list.book_count}</td>
            </tr>
            {/foreach}
        </tbody>
    </table>

    <a href="addList" class="btn btn-primary">Add a new list</a>

{include file="footer.tpl"}