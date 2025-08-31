{include file="header.tpl"}

<h3>Search the Open Library Catalogue</h3>

    <form id="book-search-form">
        <input
            type="text"
            id="book-title"
            placeholder="Enter book title or part of it"
            required
            class="form-control" 
            style="width: 50%;"
        />
        <button type="submit" class="btn btn-primary" style="margin: 10px 0;">Search</button>
    </form>
    <table id="search-table" class="display" style="width: 100%">
    <thead>
        <tr>
            <th>Title</th>
            <th>Author</th>
            <th width="10%">Link</th>
        </tr>
    </thead>
    <tbody></tbody>
    </table>

{include file="footer.tpl"}