{include file="header.tpl"}

<h1>{$book.title}</h1>

    <table class="table table-striped">
        <tbody>
            <tr><td width="15%"><strong>Author</strong></td><td align="left"><a href="/?author={$book.author|escape:'url'}">{$book.author}</a></td></tr>
            <tr><td width="15%"><strong>Series</strong></td><td>{$book.series} {if $book.seriesPosition} (#{$book.seriesPosition}){/if}</td></tr>
            <tr><td width="15%"><strong>Genre</strong></td><td align="left">{$book.genre}</td></tr>
            <tr><td width="15%"><strong>ISBN</strong></td><td>{$book.isbn}</td></tr>
            <tr><td><strong>Link</strong></td><td>{if $book.url}<a href="{$book.url}" target="_blank">{$book.title}</a>{/if}</td></tr>
            <tr><td width="15%"><strong>Format</strong></td><td><a href="/?format={$book.format|escape:'url'}">{$book.format}</a></td></tr>
            <tr><td width="15%"><strong>Source</strong></td><td><a href="/?source={$book.source|escape:'url'}">{$book.source}</a></td></tr>
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
            <tr><td><strong>Date added</strong></td><td>{$book.dateAdded}</td></tr>
        </tbody>
        </table>

    <h4>Your thoughts</h4>

    <table class="table table-striped">
        <tbody>
            <tr>
                <td width="15%"><strong>Read</strong></td>
                <td>
                    <select name="status_{$book.id}" id="statusChange_{$book.id}" class="form-select" onchange="changeStatus(this);">
                        <option value="0"{if $book.read == 0} selected{/if}>Not Read</option>
                        <option value="1"{if $book.read == 1} selected{/if}>Reading</option>
                        <option value="2"{if $book.read == 2} selected{/if}>Read</option>
                    </select>
                </td>
            </tr>
            <tr class="readDetails" {if $book.read != 2} style="display:none;"{/if}>
                <td><strong>Date Read</strong></td>
                <td>
                    <input type="date" class="form-control" name="dateTime" step="1" id="datetimePicker" required value="{$book.dateRead|date_format:'%Y-%m-%d'}">
                    <div id="error-message" style="color: red; display: none;">The date must be in the past.</div>
                </td>
            </tr>
            <tr class="readDetails" {if $book.read != 2} style="display:none;"{/if}><td><strong>Rating</strong></td><td>

                <select name="book_{$book.id}" id="ratingSelect" class="form-select">
                    <option value="0"{if $book.rating== 0} selected{/if}>No rating</option>
                    {section name=star start=1 loop=11}
                        {assign var="value" value=$smarty.section.star.index * 0.5}
                        <option value="{$value}"{if $book.rating == $value} selected{/if}>{$value}</option>
                    {/section}
                </select>
            </td></tr>
            <tr class="readDetails" {if $book.read != 2} style="display:none;"{/if}>
                <td><strong>Review</strong></td>
                <td>
                    <textarea name="book_{$book.id}" id="reviewText" class="form-control" rows="4">{$book.review}</textarea>
                </td>
            </tr>
        </tbody>
        </table>
    </table>
	<a href="#" onclick="confirmRedirect('/deleteBook?id={$book.id}'); return false;" class="btn btn-danger">Delete book</a>&nbsp;&nbsp;
	<a href="#" class="btn btn-primary" id="saveButton">Save</a>
    <input type="hidden" name="bookId" value="{$book.id}" />

{include file="footer.tpl"}