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
    <div id="catalogue-search-status" class="mb-3" role="status" aria-live="polite" aria-atomic="true" hidden>
        <span id="catalogue-search-spinner" class="spinner-border spinner-border-sm me-2" aria-hidden="true" hidden></span>
        <span id="catalogue-search-message"></span>
    </div>
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