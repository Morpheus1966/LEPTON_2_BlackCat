{include file="header.tpl"}
  <h2>{translate('Manage Droplet permissions')}</h2>
  
  <a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets">&laquo; {translate('Back to overview')} &laquo;</a><br /><br />
  
  {if $info}<div class="info ui-corner-all">{$info}</div><br />{/if}
  <form method="post" action="{$action}">
    <input type="hidden" name="tool" value="droplets" />
    <input type="hidden" name="droplet_perms" value="{$id}" />

    {if ! count($rows)}
    <div class="info ui-corner-all">{translate('No permission settings found')}</div>
    {else}
    <table class="droplets tablesorter">
	  <thead>
	    <tr>
		  <th>{translate('Name')}</th>
  		  <th>{translate('Groups')}</th>
	    </tr>
	  </thead>
	  <tbody>
	  {foreach $rows item}
        <tr>
          <td>
		    {$item.name}
		  </td>
          <td>
		    {$item.groups}
		  </td>
	    </tr>
	  {/foreach}
	  </tbody>
    </table><br />
    <input type="submit" name="save" value="{translate('Save')}" />
    <input type="submit" name="save_and_back" value="{translate('Save and Back')}" />
    <input type="submit" name="cancel" value="{translate('Cancel')}" />
  </form>
  {/if}
{include file="footer.tpl"}