<div class="codecranes_advancedsearch_input codecranes_advancedsearch_theme_0">
    <div class="codecranes_advancedsearch_products">

        {if count($listing.products) < '1'}
            <div class="cc_no_product">
                {l s='No product found' mod='codecranes_advancedsearch'}
            </div>
        {else}
            {foreach from=$listing.products item=product}
                <a href="{$product.url}" class="cc_product">
                    <div class="s_product_left">
                        {if $product.cover}
                            <img src="{$product.cover.bySize.home_default.url}" alt="{if !empty($product.cover.legend)}{$product.cover.legend}{else}{$product.name|truncate:30:'...'}{/if}" data-full-size-image-url="{$product.cover.large.url}" />
                        {else}
                            <img src="{$urls.no_picture_image.bySize.home_default.url}" alt="{$product.name|truncate:30:'...'}" />
                        {/if}
                    </div>
                    <div class="s_product_right">
                        <div class="s_product_reference">{$product.reference}</div>
                        <h3>{$product.name}</h3>
                        {if $product.has_discount}
                            <div class="s_product_price product-price has-discount">
                                <div class="product-discount">
                                    <span class="regular-price" aria-label="{l s='Regular price' mod='codecranes_advancedsearch'}">{$product.regular_price}</span>
                                </div>
                                {if $product.discount_type === 'percentage'}
                                    <span class="discount-percentage discount">{$product.discount_percentage}</span>
                                {elseif $product.discount_type === 'amount'}
                                    <span class="discount-amount discount">{$product.discount_amount_to_display}</span>
                                {/if}

                                <span class="price" aria-label="{l s='Price' mod='codecranes_advancedsearch'}">{$product.price}</span>
                            </div>
                        {else}
                            <div class="s_product_price product-price">
                                <span class="price" aria-label="{l s='Price' mod='codecranes_advancedsearch'}">{$product.price}</span>
                            </div>
                        {/if}
                        <div class="s_product_description_short">{$product.description_short nofilter}</div>
                    </div>
                </a>
            {/foreach}
        {/if}

    </div>
</div>