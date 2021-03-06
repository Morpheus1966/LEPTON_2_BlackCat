{include file="header.tpl"}
  {if $info}<div class="info ui-corner-all">{$info}</div><br />{/if}
  <form method="post" action="{$action}">
  <input type="hidden" name="tool" value="droplets" />
  {if $can_add}<input type="submit" name="add" class="ui-button ui-button-text" value="{translate('Create new')}" />{/if}
  {if $can_import}<input type="submit" name="import" class="ui-button ui-button-text" value="{translate('Import')}" />{/if}
  {if $backups}<input type="submit" name="backups" class="ui-button ui-button-text" value="{translate('List Backups')}" />{/if}
  {if $can_perms}<input type="submit" name="perms" class="ui-button ui-button-text" value="{translate('Manage global permissions')}" />{/if}
  <br /><br />
  
  {if ! count($rows)}
  <div class="info ui-corner-all">{translate('No Droplets found')}</div>
  {else}
  <table class="droplets tablesorter">
	<thead>
	  <tr>
		<th style="width:140px;"><input type="checkbox" name="checkall" id="checkall" />{translate('Actions')}</th>
		<th>{translate('Name')}</th>
		<th>{translate('Description')}</th>
		<th style="width:50px;">{translate('Active')}</th>
		<th style="width:50px;">{translate('Search')}</th>
	  </tr>
	</thead>
	<tbody>
	{foreach $rows item}
      <tr>
        <td>
          {if $can_export || $can_delete}<input type="checkbox" name="markeddroplet[]" id="markeddroplet_{$item.id}" value="{$item.id}" />{/if}
          {if $can_modify || $item.user_can_modify_this}<a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;edit={$item.id}" title="{translate('Modify')}">
			<img src="{$IMGURL}/modify.png" alt="{translate('Modify')}" />
   		  </a>
          <a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;copy={$item.id}">
            <img src="{$IMGURL}/copy.png" title="{translate('Duplicate')}" />
          </a>
          {/if}
          {if $can_delete}
		  <a class="del" href="javascript: confirm_link('{translate( 'Are you sure?' )}','{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;del={$item.id}')">
		    <img src="{$IMGURL}/delete.png" title="{translate('Delete')}" />
		  </a>
		  {/if}
          {if $can_perms}
          <a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;droplet_perms={$item.id}">
            <img src="{$IMGURL}/key.png" title="{translate('Droplet permissions')}" />
          </a>
		  {/if}
          <a href="#" class="tooltip">
            <img src="{$IMGURL}/info.png" title="{translate('Info')}" />
			<span class="comment">{$item.comments}</span>
          </a>
          {if $item.datafile}
          <a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;datafile={$item.id}">
            <img src="{$IMGURL}/edit.png" title="{translate('Edit datafile')}" />
          </a>
          {/if}
          {if $item.valid_code}<img src="{$IMGURL}/droplet.png" title="{translate('Valid')}" />{else}<img src="{$IMGURL}/invalid.gif" title="{translate('Invalid')}" />{/if}
		</td>
        <td>
		  {$item.name}
		</td>
        <td>{$item.description}</td>
        <td>
		  {if $can_modify || $item.user_can_modify_this}
		  <a href="{$CAT_ADMIN_URL}/admintools/tool.php?tool=droplets&amp;toggle={$item.id}">{if $item.active}<img src="{$IMGURL}/ok.png" title="{translate('Yes')}" />{else}<img src="{$IMGURL}/no.png" title="{translate('No')}" />{/if}</a>
		  {/if}
		</td>
		<td>
          {if $item.is_in_search}
		  <img src="{$IMGURL}/magnifier.png" alt="{translate('Droplet is registered in Search')}" title="{translate('Droplet is registered in Search')}" />
		  {else}
		  <img src="{$IMGURL}/magnifier_grey.png" alt="{translate('Droplet is NOT registered in Search')}" title="{translate('Droplet is NOT registered in Search')}" />
		  {/if}
		</td>
	  </tr>
	{/foreach}
	</tbody>
  </table><br />
  {if $can_export || $can_delete}{translate('marked')}:<br />
  {if $can_export}<input type="submit" name="export" value="{translate('Export')}" />{/if}
  {if $can_delete}<input type="submit" name="delete" value="{translate('Delete')}" />{/if}
  {/if}
  </form>
  {/if}
{include file="footer.tpl"}