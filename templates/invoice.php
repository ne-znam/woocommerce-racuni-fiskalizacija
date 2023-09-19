<!DOCTYPE html>
<html <?php language_attributes(); ?>>>

<head>
	<meta charset="UTF-8">
	<!--  Meta stuff -->
	<title>Račun <?php echo $invoice_number ?></title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link
		href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap'"
		rel="stylesheet">

</head>

<body>
<div class="full flex racun-wrapper">
	<div class="full racun">
		<div class="full racun-header bottom-margin">

			<div class="float-left right-margin half">
				<?php if (has_custom_logo()) {
					the_custom_logo();
					?>
				<?php } ?>
			</div>
			<div class="float-right half font-x2">
				<p class="full bold"><?php echo $business_data['name'] ?></p>
				<p class="full"><?php echo $business_data['address1']  ?></p>
				<p class="full"><?php echo $business_data['address2']  ?></p>
				<p class="full"><?php echo $business_data['post_code']  ?> <?php echo $business_data['city'] ?></p>
				<p class="full">OIB: <?php echo $business_data['oib'] ?></p>
			</div>
		</div>
		<div class="full half float-left racun-info">
			<div class="full">
				<div class="float-left two-thirds bold">RAČUN broj: </div><div class="float-left third bold"><?php echo $invoice_number ?></div>
				<div class="float-left two-thirds">Datum izdavanja računa: </div><div class="float-left  third bold"><?php echo $meta[$slug . '_created_at'] ?></div>
				<div class="float-left two-thirds">Mjesto izdavanja: </div><div class="float-left  third bold"><?php echo $business_data['city'] ?></div>
				<div class="float-left two-thirds">OIB: </div><div class="float-left  third bold"><?php echo $business_data['oib'] ?></div>
			</div>
		</div>
		<div class="half float-left half racun-client">
			<div class="float-left two-thirds bold">Klijent: </div>
			<div class="float-left third">
				<p class="full"><?php echo $content['billing_address']['name'] ?></p>
				<p class="full"><?php echo $content['billing_address']['first_line'] ?></p>
				<p class="full"><?php echo $content['billing_address']['second_line'] ?></p>
				<p class="full"><?php echo $content['billing_address']['postcode'] ?> <?php echo $content['billing_address']['city'] ?></p>
				<p class="full"><?php echo $content['billing_address']['country'] ?></p>
			</div>
		</div>
		<div class="full racun-table">
			<div class="full red-row bold center-text table-row">
				<div class="float-left thirty">Opis</div>
				<div class="float-left tenth">Količina (h)</div>
				<div class="float-left fifty">Neto cijena</div>
				<div class="float-left tenth">Ukupno neto</div>
			</div>
			<?php foreach ($content['line_items'] as $item) { ?>
			<div class="full table-row bottom-border center-text stretch">
				<div class="float-left thirty"><?php echo $item['name'] ?></div>
				<div class="float-left tenth"><?php echo $item['quantity'] ?></div>
				<div class="float-left fifty"><?php echo $item['unit_price'] ?></div>
				<div class="float-left tenth right-align"><?php echo $item['total'] ?></div>
			</div>
			<?php } ?>
			<div class="full table-row center-text">
				<div class="third float-right">
					<div class="float-left two-thirds bold">Osnovica: </div><div class="float-right third bold right-align"><?php echo $content['total'] ?></div>
					<?php foreach ( $content['tax_rates'] as $tax_rate ) { ?>
					<div class="float-left two-thirds bold"><?php echo  $tax_rate['label'] ?> <?php echo $tax_rate['percent'] ?>%: </div><div class="float-right third bold right-align"><?php echo $tax_rate['tax'] ?></div>
					<?php } ?>
				</div>
			</div>
			<div class="full red-row table-row bold center-text">
				<div class="third float-right">
					<div class="float-left two-thirds bold">UKUPNO: </div><div class="float-right third bold right-align"><?php echo $content['total'] ?></div>
				</div>
			</div>
		</div>
		<div class="full" style="margin-top: 30px;">
			<p class="full">Račun izrađen: Račun je automatski generiran putem aplikacije <?php echo get_bloginfo('name'); ?></p>
			<p class="full">Odgovorna osoba: <?php echo $business_data['operator'] ?></p>
			<p class="full">Plaćanje: Kartica</p>
			<p>ZKI: <?php echo $meta[$slug . '_zki'] ?></p>
			<p>JIR: <?php echo $meta[$slug . '_jri'] ?></p>
			<p style="margin: 10px;"><img src="data:image/png;base64,<?php echo  \Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG($meta[$slug . '_qr_code_link'], 'QRCODE') ?>"></p>
		</div>
		<p style="font-size: 10px; margin-top: 10px"><?php echo $content['notes'] ?></p>
	</div>
</div>
<style>
	/* CSS Reset */

	html {
		margin: 0;
		padding: 0;
		border: 0;
		overflow-x: hidden;
	}

	body,
	div,
	div,
	object,
	iframe,
	h1,
	h2,
	h3,
	h4,
	h5,
	h6,
	p,
	blockquote,
	pre,
	a,
	abbr,
	acronym,
	address,
	code,
	del,
	dfn,
	em,
	img,
	q,
	dl,
	dt,
	dd,
	ol,
	ul,
	li,
	fieldset,
	form,
	label,
	legend,
	table,
	caption,
	tbody,
	tfoot,
	thead,
	tr,
	th,
	td,
	article,
	aside,
	dialog,
	figure,
	footer,
	header,
	hgroup,
	nav,
	section {
		margin: 0;
		padding: 0;
		border: 0;
		font-weight: inherit;
		font-style: inherit;
		font-size: 100%;
		font-family: Quicksand, sans-serif;
		vertical-align: baseline;
		list-style: none;
	}

	.main-container {
		width: 100%;
		overflow-x: hidden;
		position: relative;
	}

	/* Basics */

	body {
		-webkit-font-smoothing: antialiased;
	}

	* {
		-webkit-box-sizing: border-box;
		-moz-box-sizing: border-box;
		-ms-box-sizing: border-box;
		-o-box-sizing: border-box;
		box-sizing: border-box;
	}

	a {
		text-decoration: none;
		color: inherit;
		cursor: pointer;
		-webkit-transition: all 0.3s;
		-moz-transition: all 0.3s;
		-ms-transition: all 0.3s;
		-o-transition: all 0.3s;
		transition: all 0.3s;
	}

	:focus {
		outline: none;
	}

	/* Typography */

	b,
	strong,
	.bold {
		font-weight: 600;
	}

	.superbold {
		font-weight: 900;
	}

	i,
	em,
	.italic {
		font-style: italic;
	}

	.center-text {
		text-align: center;
	}

	.left-text {
		text-align: left;
	}

	.right-text {
		text-align: right;
	}

	.float-right {
		float: right;
	}

	.float-left {
		float: left;
	}

	.white-text {
		color: white;
	}

	.clickable {
		cursor: pointer;
	}

	.animate,
	svg {
		-webkit-transition: all 0.3s;
		-moz-transition: all 0.3s;
		-ms-transition: all 0.3s;
		-o-transition: all 0.3s;
		transition: all 0.3s;
		-webkit-transform: translateZ(0);
		-moz-transform: translateZ(0);
		-ms-transform: translateZ(0);
		-o-transform: translateZ(0);
		transform: translateZ(0);
	}

	@keyframes spin {
		to {
			transform: rotate(360deg);
		}
	}

	/* Layout */

	.flex {
		display: block;
		float: left;
		flex-flow: row;
		flex-wrap: wrap;
		align-items: flex-start;
		align-content: flex-start;
	}

	.flex:after {
		content: "";
		display: table;
		clear: both;
	}


	.half {
		width: 49.99%;
	}

	.third {
		width: 33.33%;
	}

	.two-thirds {
		width: 66.66%;
	}

	.full {
		width: 100%;
	}



	/* Functions */


	.relative {
		position: relative;
	}

	.center-text {
		text-align: center;
	}

</style>
<style>
	.racun-wrapper {
		padding: 8vw 4vw;
	}
	html {
		font-size: 20px;
		line-height: 1.15em;
		font-family: Quicksand, sans-serif;
		font-weight: 400;
		font-style: normal;
		color: #111;
	}
	.racun {
		font-size: 14px;
		font-size: 0.7rem;
	}
	.bigger {
		font-size: 18px;
		font-size: 0.9rem;
	}
	.right-margin {
		margin-right: 24px;
	}
	.bottom-margin {
		margin-bottom: 24px;
	}
	.racun-info {
		padding-bottom: 16px;
		margin-bottom: 16px;
	}
	.racun-client {
		margin-bottom: 48px;
	}
	.table-row {
		padding: 10px;
	}
	.table-row > * {
		padding: 15px;
		display: block;
		justify-content: center;
		align-items: center;
		align-content: center;
	}
	.thirty {
		width: 29.99%;
	}
	.tenth {
		width: 9.99%;
	}
	.fifty {
		width: 49.99%;
	}
	.bottom-border {
		border-bottom: 1px dashed #111;
	}
	.red-row {
		background-color: #dadada;
		color: #3b3b3b;
		border-bottom: none;
		border-radius: 10px;
	}
	.red-row > * {
		padding: 8px 4px;
	}
	.right-align {
		justify-content: flex-end;
	}
	.font-x2 {
		font-size: 1rem;
	}
</style>
</body>

</html>
