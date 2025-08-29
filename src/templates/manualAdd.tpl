{include file="header.tpl"}

<h1>Manually add a book</h1>

    <table class="table table-striped">
        <tbody>
            <tr><td width="15%"><strong>Author</strong></td><td align="left"><input type="text" class="form-control" id="authorInput" /></td></tr>
            <tr><td width="15%"><strong>Series</strong></td><td><input type="text" class="form-control" id="seriesInput" /> </td></tr>
            <tr><td width="15%"><strong>Genre</strong></td><td align="left"><input type="text" class="form-control" id="genreInput" /> </td></tr>
            <tr><td width="15%"><strong>ISBN</strong></td><td><input type="text" class="form-control" id="isbnInput" /> </td></tr>
            <tr><td><strong>Link</strong></td><td><input type="text" class="form-control" id="urlInput" /> </td></tr>
            <tr>
                <td><strong>Format</strong></td>
                <td>
                    <select class="form-select" id="formatList">
                        {foreach from=$formats item=format}
                            <option value="{$format.id}">{$format.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td><strong>Source</strong></td>
                <td>
                    <select class="form-select" id="sourceList">
                        {foreach from=$sources item=source}
                            <option value="{$source.id}" >{$source.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td><strong>Lists</strong></td>
                <td>
                    <select class="form-select" id="bookList" multiple="multiple">
                        {foreach from=$lists item=list}
                            <option value="{$list.id}" {if $list.selected} selected{/if}>{$list.name}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr><td><strong>Date added</strong></td><td></td></tr>
        </tbody>
        </table>

    <h4>Your thoughts</h4>

    <table class="table table-striped">
        <tbody>
            <tr>
                <td width="15%"><strong>Read</strong></td>
                <td>
                    <select name="status" id="statusSelect" class="form-select">
                        <option value="0">Not Read</option>
                        <option value="1">Reading</option>
                        <option value="2">Read</option>
                    </select>
                </td>
            </tr>
            <tr class="readDetails"  >
                <td><strong>Date Read</strong></td>
                <td>
                    <input type="date" class="form-control" name="dateTime" step="1" id="datetimePicker" required value="{$book.dateRead|date_format:'%Y-%m-%d'}">
                    <div id="error-message" style="color: red; display: none;">The date must be in the past.</div>
                </td>
            </tr>
            <tr class="readDetails" ><td><strong>Rating</strong></td><td>

                <select name="book" id="ratingSelect" class="form-select">
                    <option value="0">No rating</option>
                    {section name=star start=1 loop=11}
                        {assign var="value" value=$smarty.section.star.index * 0.5}
                        <option value="{$value}">{$value}</option>
                    {/section}
                </select>
            </td></tr>
            <tr class="readDetails" >
                <td><strong>Review</strong></td>
                <td>
                    <textarea name="book" id="reviewText" class="form-control" rows="4"></textarea>
                </td>
            </tr>
        </tbody>
        </table>
    </table>
	<a href="#" class="btn btn-primary" id="saveButton">Save</a>

{include file="footer.tpl"}