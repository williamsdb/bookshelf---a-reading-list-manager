{include file="header.tpl"}

<h1>View & edit book details</h1>
<form role="form" action="/updateBook" method="post">
    <table class="table table-striped">
        <tbody>
            <tr><td width="15%"><strong>Title<sup>*</sup></strong></td><td align="left"><input type="text" class="form-control" id="titleInput" name="titleInput" value="{$book.title}" required /></td></tr>
            <tr>
                <td width="15%"><strong>Author<sup>*</sup></strong></td>
                <td>
                    <input type="text" class="form-control" id="authorInput" name="authorInput" list="authorSuggestions" autocomplete="off" value="{$book.author}" required />
                    <datalist id="authorSuggestions"></datalist>
                    <script>
                    {literal}
                    (function () {
                        const input = document.getElementById('authorInput');
                        const list = document.getElementById('authorSuggestions');
                        // get data from back end
                        const ENDPOINT = '/authorSearch';

                        let timer = null;
                        let controller = null;

                        input.addEventListener('input', function () {
                            const q = this.value.trim();
                            if (q.length < 2) {
                                list.innerHTML = '';
                                if (controller) controller.abort();
                                return;
                            }
                            if (timer) clearTimeout(timer);
                            timer = setTimeout(async () => {
                                if (controller) controller.abort();
                                controller = new AbortController();
                                try {
                                    const res = await fetch(ENDPOINT + '?q=' + encodeURIComponent(q), { signal: controller.signal });
                                    if (!res.ok) return;
                                    const data = await res.json(); // expect array of strings
                                    const unique = [...new Set((data || []).filter(Boolean))].slice(0, 20);
                                    list.innerHTML = unique.map(s => '<option value="' + escapeHtml(s) + '"></option>').join('');
                                } catch (e) {
                                    // silent
                                }
                            }, 250);
                        });

                        function escapeHtml(str) {
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                        }
                    })();
                    {/literal}
                    </script>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table width="100%" border="0">
                        <tr>
                            <td width="15%"><strong>Series</strong></td>
                            <td width="70%">
                                <input type="text" class="form-control" id="seriesInput" name="seriesInput" list="seriesSuggestions" autocomplete="off" value="{$book.series}" />
                                <datalist id="seriesSuggestions"></datalist>
                                <script>
                                {literal}
                                (function () {
                                    const input = document.getElementById('seriesInput');
                                    const list = document.getElementById('seriesSuggestions');
                                    // get data from back end
                                    const ENDPOINT = '/seriesSearch';

                                    let timer = null;
                                    let controller = null;

                                    input.addEventListener('input', function () {
                                        const q = this.value.trim();
                                        if (q.length < 2) {
                                            list.innerHTML = '';
                                            if (controller) controller.abort();
                                            return;
                                        }
                                        if (timer) clearTimeout(timer);
                                        timer = setTimeout(async () => {
                                            if (controller) controller.abort();
                                            controller = new AbortController();
                                            try {
                                                const res = await fetch(ENDPOINT + '?q=' + encodeURIComponent(q), { signal: controller.signal });
                                                if (!res.ok) return;
                                                const data = await res.json(); // expect array of strings
                                                const unique = [...new Set((data || []).filter(Boolean))].slice(0, 20);
                                                list.innerHTML = unique.map(s => '<option value="' + escapeHtml(s) + '"></option>').join('');
                                            } catch (e) {
                                                // silent
                                            }
                                        }, 250);
                                    });

                                    function escapeHtml(str) {
                                        return String(str)
                                            .replace(/&/g, '&amp;')
                                            .replace(/</g, '&lt;')
                                            .replace(/>/g, '&gt;')
                                            .replace(/"/g, '&quot;')
                                            .replace(/'/g, '&#039;');
                                    }
                                })();
                                {/literal}
                                </script>
                            </td>
                            <td width="5%"><strong>&nbsp;&nbsp;&nbsp;#</strong></td><td align="left"><input type="number" class="form-control" id="numberInput" name="numberInput"  value="{$book.seriesPosition}" /></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td width="15%"><strong>Genre</strong></td>
                <td>
                    <input type="text" class="form-control" id="genreInput" name="genreInput" list="genreSuggestions" autocomplete="off"  value="{$book.genre}" />
                    <datalist id="genreSuggestions"></datalist>
                    <script>
                    {literal}
                    (function () {
                        const input = document.getElementById('genreInput');
                        const list = document.getElementById('genreSuggestions');
                        // get data from back end
                        const ENDPOINT = '/genreSearch';

                        let timer = null;
                        let controller = null;

                        input.addEventListener('input', function () {
                            const q = this.value.trim();
                            if (q.length < 2) {
                                list.innerHTML = '';
                                if (controller) controller.abort();
                                return;
                            }
                            if (timer) clearTimeout(timer);
                            timer = setTimeout(async () => {
                                if (controller) controller.abort();
                                controller = new AbortController();
                                try {
                                    const res = await fetch(ENDPOINT + '?q=' + encodeURIComponent(q), { signal: controller.signal });
                                    if (!res.ok) return;
                                    const data = await res.json(); // expect array of strings
                                    const unique = [...new Set((data || []).filter(Boolean))].slice(0, 20);
                                    list.innerHTML = unique.map(s => '<option value="' + escapeHtml(s) + '"></option>').join('');
                                } catch (e) {
                                    // silent
                                }
                            }, 250);
                        });

                        function escapeHtml(str) {
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                        }
                    })();
                    {/literal}
                    </script>
                </td>
            </tr>
            <tr><td width="15%"><strong>ISBN</strong></td><td><input type="text" class="form-control" id="isbnInput" name="isbnInput" value="{$book.isbn}" /> </td></tr>
            <tr>
                <td>
                    <strong>Link</strong></td><td><input type="url" class="form-control" id="urlInput" name="urlInput" value="{$book.url}" />
                    {if $book.url}<small>Go to <a href="{$book.url}" target="_blank">link</a></small>.{/if}
                </td>
            </tr>
            <tr>
                <td><strong>Format<sup>*</sup></strong></td>
                <td>
                    <select class="form-select" id="formatList" name="formatList" required>
                        {foreach from=$formats item=format}
                            <option value="{$format.id}" {if $format.id == $book.formatId}selected{/if}>{$format.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td><strong>Lists</strong></td>
                <td>
                    <select class="form-select" id="bookList" name="bookList[]" multiple="multiple">
                        {assign var="bookList" value=$book.list|split:","}
                        {foreach from=$lists item=list}
                            <option value="{$list.id}" {if in_array($list.id, $bookList)}selected{/if}>{$list.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td width="15%"><strong>Read</strong></td>
                <td>
                    <select name="status" id="statusChange" class="form-select" onchange="changeStatusNoUpdate(this);">
                        <option value="0" {if $book.read == 0}selected{/if}>Not Read</option>
                        <option value="1" {if $book.read == 1}selected{/if}>Reading</option>
                        <option value="2" {if $book.read == 2}selected{/if}>Read</option>
                    </select>
                </td>
            </tr>
            <tr class="readDetailsAdd" style="display:none;">
                <td><strong>Date Read</strong></td>
                <td>
                    <input type="date" class="form-control" name="dateRead" step="1" id="dateReadPickerNoUpdate" value="{$book.dateRead|date_format:"%Y-%m-%d"}" />
                    <div id="error-message" style="color: red; display: none;">The date must be in the past.</div>
                </td>
            </tr>
            <tr class="readDetailsAdd" style="display:none;">
                <td><strong>Rating</strong></td>
                <td>
                    <select name="rating" id="ratingSelectNoUpdate" class="form-select">
                        <option value="0">No rating</option>
                        {section name=star start=1 loop=11}
                            {assign var="value" value=$smarty.section.star.index * 0.5}
                            <option value="{$value}"{if $book.rating == $value} selected{/if}>{$value}</option>
                        {/section}
                    </select>
                </td>
            </tr>
            <tr class="readDetailsAdd"  style="display:none;">
                <td><strong>Review</strong></td>
                <td>
                    <textarea name="review" id="reviewText" class="form-control" rows="4">{if $book.review}{$book.review|escape:"html"}{/if}</textarea>
                </td>
            </tr>
        </tbody>
    </table>
    <p><small>Author, Title and Format are all mandatory</small></p>
    <input type="hidden" name="bookId" value="{$book.id}" />
    <button type="submit" class="btn btn-primary" id="bookAdd" name="bookAdd">Save</button>
</form>
<script>
  // run once after page load
  window.addEventListener("DOMContentLoaded", function () {
    changeStatusNoUpdate(document.getElementById("statusChange"));
  });
</script>
{include file="footer.tpl"}