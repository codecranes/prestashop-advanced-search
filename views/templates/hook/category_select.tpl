{if $cc_categories && count($cc_categories)}
    <select class="cc_search_categories" name="c">
        <option value="0">{l s='All categories' mod='codecranes_advancedsearch'}</option>

        {foreach from=$cc_categories item="category"}
            <option value="{$category.id_category}" {if $search_category == $category.id_category} selected="selected" {/if}>{$category.name}</option>
        {/foreach}
    </select>
{/if}