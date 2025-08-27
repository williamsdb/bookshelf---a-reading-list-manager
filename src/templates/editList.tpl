{include file="header.tpl"}

<h3>Edit a list</h3>
<form role="form" action="/updateList" method="post">
    <div class="mb-3">
        <label for="listName" class="form-label">List name</label>
        <input type="text" class="form-control" id="listName" name="listName" placeholder="list name" required autofocus maxlength="100" value={$listName}>
    </div>
    <div class="mb-3">
        <label for="default" class="form-label">Default?</label>
        <label class="switch">
            <input type="checkbox" id="showAllToggle" name="default" {if $default}checked disabled{/if}>
            <span class="slider round"></span>
        </label>
        {if $default}<small>(You cannot unset the default list. Make another list default instead.)</small>{/if}
    </div>
    <input type="hidden" id="listId" name="id" value="{$id}">
    <button type="submit" class="btn btn-primary" id="addSave" name="addSave" value="addSave">Save</button>
</form>

{include file="footer.tpl"}