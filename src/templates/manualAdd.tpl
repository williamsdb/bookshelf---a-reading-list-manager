{include file="header.tpl"}

<h1>Manually add a book</h1>
<form role="form" action="/createBook" method="post">
    <table class="table table-striped">
        <tbody>
            <tr><td width="15%"><strong>Title</strong></td><td align="left"><input type="text" class="form-control" id="titleInput" name="titleInput" required /></td></tr>
            <tr>
                <td width="15%"><strong>Author</strong></td>
                <td>
                    <input type="text" class="form-control" id="authorInput" name="authorInput" list="authorSuggestions" autocomplete="off" required />
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
                                <input type="text" class="form-control" id="seriesInput" name="seriesInput" list="seriesSuggestions" autocomplete="off" />
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
                            <td width="5%"><strong>&nbsp;&nbsp;&nbsp;#</strong></td><td align="left"><input type="number" class="form-control" id="numberInput" name="numberInput" /></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr><td width="15%"><strong>Genre</strong></td><td align="left"><input type="text" class="form-control" id="genreInput" name="genreInput" /> </td></tr>
            <tr><td width="15%"><strong>ISBN</strong></td><td><input type="text" class="form-control" id="isbnInput" name="isbnInput" /> </td></tr>
            <tr><td><strong>Link</strong></td><td><input type="url" class="form-control" id="urlInput" name="urlInput" /> </td></tr>
            <tr>
                <td><strong>Format</strong></td>
                <td>
                    <select class="form-select" id="formatList" name="formatList" required>
                        <option value="" disabled selected>Select a format</option>
                        {foreach from=$formats item=format}
                            <option value="{$format.id}">{$format.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td><strong>Lists</strong></td>
                <td>
                    <select class="form-select" id="bookList" name="bookList[]" multiple="multiple">
                        {foreach from=$lists item=list}
                            <option value="{$list.id}">{$list.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
        </tbody>
    </table>
    <button type="submit" class="btn btn-primary" id="bookAdd" name="bookAdd">Save</button>
</form>

{include file="footer.tpl"}