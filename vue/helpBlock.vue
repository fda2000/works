<template>
	<div class="helpBlock">
		<p class="show"><a href="javascript:void(0)" @click="show = !show">Информация...</a></p>
		<div class="slot" v-if="show">
			<slot></slot>
		</div>
	</div>
</template>
<script>
export default {
	data: function () {
		return {
			show: false
		}
	}
}
</script>
<style scoped lang="scss">
.helpBlock .show a {
	color: green;
	text-decoration: none;
	border-bottom: 1px dashed green;
}

.helpBlock .show a:hover {
	border-bottom-style: solid;
}

.helpBlock .slot {
	margin: 20px 50px;
	padding: 10px;
	border: 5px #aaa double;
	background: #EEE;
}

.helpBlock h2 {
	text-align: center;
}
</style>
