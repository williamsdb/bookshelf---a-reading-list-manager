{include file="header.tpl"}

<h3>Search to find a book by ISBN</h3>
    <p id="h2title">Search by ISBN to add to your reading list</p>
    <form method="post" id="isbn-form" action="/fetch-book">
        <input
            type="text"
            name="isbn"
            placeholder="Enter ISBN number"
            required
        />
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div id="result"></div>

{include file="footer.tpl"}