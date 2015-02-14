<td colspan="2">
<table width="100%" class="captcha">
	<tr>
		<td width="50%" align="center">
			<p class="captcha_title">Input text from the image</p>
			<p class="captcha_text">Press <a href="javascript:void(0);" onclick="captcha_update()">here</a> if image is not readable.</p>
		</td>
		<td width="50%" align="center">
		<img src="" id="captcha_img" style="cursor:pointer;" onclick="captcha_update()">
			<br/>
			<input type="text" autocomplete="off" class="formgen_input" name="captcha" style="margin-top:2px;width:120px;text-transform:lowercase;text-align:center;"/>
		</td>
	</tr>
</table>
<script type="text/javascript">
	function captcha_update()
	{
		document.getElementById('captcha_img').src = '{{$_url}}/kcaptcha/?' + Math.random() + '&{{session_name()}}={{session_id()}}';
	}
	captcha_update();
</script>
</td>