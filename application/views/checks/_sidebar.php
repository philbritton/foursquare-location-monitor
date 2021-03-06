<?php if (is_array($checks) && count($checks) > 0): ?>
<form>
<fieldset>
<legend>Monitored Venues</legend>

<?php if (count($checks) > 10): ?>
<select name="check_list" onchange="window.location=$(this).val()">
	<option value="">(Select a Venue)</option>
	<?php foreach ($checks as $check): ?>
	<?php if ($check->active != '1') continue; ?>
	<option value="<?php echo site_url('foursquare/venue') .'/'. ($check->venue_id); ?>"><?php echo ($check->check_title); ?></option>
	<?php endforeach; ?>
</select>

<?php else: ?>

<ul>
<?php foreach ($checks as $check): ?>
	<li><a href="<?php echo site_url('foursquare/venue') .'/'. ($check->venue_id); ?>"><?php echo ($check->check_title); ?></a></li>
<?php endforeach; ?>
</ul>

<?php endif; ?>

<?php else: ?>
<p>
	<em>No venues monitored. <a href="<?php echo site_url('foursquare/search'); ?>">Add via Search</a></em>.
</p>
<?php endif; ?>

</fieldset>
</form>