{extends file="helpers/form/form.tpl"}

{block name="fieldset"}
    {if isset($fieldset.type)}
        {if $fieldset.type == 'blank'}
            <div class="fieldset_blank clearfix">
                {if isset($fieldset['form']['submit']) && !empty($fieldset['form']['submit'])}
                    <button type="submit" value="1" id="{if isset($fieldset['form']['submit']['id'])}{$fieldset['form']['submit']['id']}{else}{$table}_form_submit_btn{/if}{if $smarty.capture.form_submit_btn > 1}_{($smarty.capture.form_submit_btn - 1)|intval}{/if}" name="{if isset($fieldset['form']['submit']['name'])}{$fieldset['form']['submit']['name']}{else}{$submit_action}{/if}{if isset($fieldset['form']['submit']['stay']) && $fieldset['form']['submit']['stay']}AndStay{/if}" class="{if isset($fieldset['form']['submit']['class'])}{$fieldset['form']['submit']['class']}{else}btn btn-default pull-right{/if}">
                        <i class="{if isset($fieldset['form']['submit']['icon'])}{$fieldset['form']['submit']['icon']}{else}process-icon-save{/if}"></i>
                        {$fieldset['form']['submit']['title']}
                    </button>
                {/if}
            </div>
        {elseif $fieldset.type == 'collapse'}
            <div class="cc_collapse">
                {$smarty.block.parent}
            </div>
        {/if}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name="input"}
    {if $input.type == 'cccsstextarea'}
        <textarea name="cccsstextarea_{$input.name}" class="hidden">{$fields_value[$input.name]|escape:'html':'UTF-8'}</textarea>

        <div id="cccsstextarea_{$input.name}">{$fields_value[$input.name]|escape:'html':'UTF-8'}</div>

        <script type="text/javascript">
            var editor = ace.edit("cccsstextarea_{$input.name}");
            editor.setTheme("ace/theme/twilight");
            editor.getSession().setMode("ace/mode/css");

            var textarea = $('textarea[name="cccsstextarea_{$input.name}"]').hide();

            textarea.text(editor.getSession().getValue());

            editor.getSession().on('change', function() {
                textarea.text(editor.getSession().getValue());
            });
        </script>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}