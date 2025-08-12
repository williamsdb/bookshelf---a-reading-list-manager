{include file="header.tpl"}

<h3>Add a new list</h3>
<form role="form" action="/createList" method="post">
    <div class="mb-3">
        <label for="listName" class="form-label">List name</label>
        <input type="text" class="form-control" id="listName" name="listName" placeholder="list name" required autofocus maxlength="100">
    </div>
    <div class="mb-3">
        <label for="default" class="form-label">Default?</label>
        <label class="switch">
            <input type="checkbox" id="showAllToggle" name="default">
            <span class="slider round"></span>
        </label>
    </div>
    <button type="submit" class="btn btn-primary" id="addSave" name="addSave" value="addSave">Save</button>
</form>

{include file="footer.tpl"}