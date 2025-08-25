{include file="header.tpl"}

<h3>Book Details</h3>

        <strong>Book Details</strong>
        <table class="table table-striped">
        <tbody>
            <tr>
                <td><strong>Title</strong></td><td id="book-title">{$title}</td>
            </tr>
            <tr>
                <td><strong>Author(s)</strong></td><td><span id="authors">{$author}</span></td>
            </tr>
            <tr>
                <td><strong>Subject</strong></td><td><span id="subject">{$subject}</span></td>
            </tr>
            <tr>
                <td><strong>Format</strong></td><td><span>{$format}</span></td>
            </tr>
            <tr>
                <td><strong>Publisher</strong></td><td><span>{$publisher}</span></td>
            </tr>
            <tr>
                <td><strong>Published Date</strong></td><td><span>{$publish_date}</span></td>
            </tr>
            <tr>
                <td><strong>ISBN</strong></td><td><span id="isbn">{$isbn}</span></td>
            </tr>
            <tr>
                <td><strong>Book Link</strong></td><td><button class="btn btn-primary" onclick="window.open('{$url}', '_blank')" style="display: block;">Open link</button></td>
            </tr>
        </tbody>
        </table>
        <button id="read-button" class="btn btn-primary">Add to Reading List</button>
        <script>
            const readButton = document.getElementById("read-button");
            if (readButton) {
            readButton.addEventListener("click", () => {
                console.log("Read button clicked");
                recordDetails("{$recordDetails}");
            });
            }
        </script>

{include file="footer.tpl"}