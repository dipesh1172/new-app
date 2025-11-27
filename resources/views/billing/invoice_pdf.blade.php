<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
body {
	font-family: 'san-serif';
	font-size: 10;
}

.invoice-items {
	border-collapse: collapse;
}

.invoice-items th {
	border: 1px solid #444444;
	padding: 5px;
	background-color: #444444;
	color: #FFFFFF;
}

.invoice-items td {
	border: 1px solid #444444;
	padding: 5px;
}
</style>
</head>
<body>
<table width="100%">
	<tr>
		<td style="padding-right: 20px;">
			<table width="100%">
				<tr>
					<td>
						<h2><img src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png" /></h2>
					</td>
					<td style="text-align: right;">
						<h3>
							<strong>Invoice for TPV Services</strong><br />
							<small>Period: {{ date("m/d/Y", strtotime($invoice->invoice_start_date)) }} - {{ date("m/d/Y", strtotime($invoice->invoice_end_date)) }}</small>
						</h3>
					</td>
				</tr>
			</table>

			<br />

			<table width="100%">
				<tr>
					<td>
						<h5>
							<strong>Invoice Number: {{ $invoice->invoice_number }}</strong>
                            @if($invoice->purchase_order_no !== null)
                            <br>
                            PO # {{ $invoice->purchase_order_no}}
                            @endif
						</h5>

						<h5>
							<address>
								{{ $invoice->legal_name !== null && trim($invoice->legal_name) !== '' ? $invoice->legal_name : $invoice->name }}<br>
								{{ $invoice->address }}<br>
								{{ $invoice->city }}, {{ $invoice->state }} {{ $invoice->zip }}
							</address>
						</h5>
					</td>
					<td style="text-align: right;">
						<h5>
							<strong>Due Date: {{ date("F j, Y", strtotime($invoice->invoice_due_date)) }}</strong>
						</h5>
					</td>
				</tr>
			</table>

			<br />

			<table width="100%" class="invoice-items">
				<thead>
					<tr>
						<td><strong>Item</strong></td>
						<td style="text-align: center;"><strong>Price</strong></td>
						<td style="text-align: center;"><strong>Quantity</strong></td>
						<td style="text-align: right;"><strong>Totals</strong></td>
					</tr>
				</thead>
				<tbody>
					@php $amount = 0; @endphp
					@if (count($live) > 1)
						<tr>
							<td>Live Minutes</td>
							<td style="text-align: center;"><b>${{ number_format($live_avg_rate, 3, ".", ",") }}</b></td>
							<td style="text-align: center;"><b>{{ number_format($live_minutes, 2, ".", ",") }}</b></td>
							<td style="text-align: right;"><b>${{ number_format($live_total, 2, ".", ",") }}</b></td>
						</tr>
						@foreach ($live as $i)
						<tr>
							<td class="text-right text-muted">
								{{ $i->note }}
							</td>
							<td class="text-center text-muted">${{ number_format($i->rate, 2, ".", ",") }}</td>
							<td class="text-center text-muted">{{ number_format($i->quantity, 2, ".", ",") }}</td>
							<td class="text-right text-muted">${{ number_format($i->total, 2, ".", ",") }}</td>
						</tr>
						@php $amount += $i->total; @endphp
						@endforeach
					@else
						@php
						$rate = (isset($live) && isset($live[0])) ? $live[0]->rate : 0;
						$quantity = (isset($live) && isset($live[0])) ? $live[0]->quantity : 0;
						$total = (isset($live) && isset($live[0])) ? $live[0]->total : 0;
						@endphp
						<tr>
							<td>Live Minutes</td>
							<td style="text-align: right;">${{ number_format($rate, 2, ".", ",") }}</td>
							<td style="text-align: right;">{{ number_format($quantity, 2, ".", ",") }}</td>
							<td style="text-align: right;">${{ number_format($total, 2, ".", ",") }}</td>
						</tr>
						@if (isset($live) && isset($live[0]))
							@php $amount += @$live[0]->total; @endphp
						@else
							@php $amount += 0; @endphp
						@endif
					@endif

					@foreach ($items as $i)
					<tr>
						<td>
							{{ $i->item_desc }}

							@if ($i->note)
								({{ $i->note }})
							@endif
						</td>
						<td style="text-align: right;">
                            @if ($i->item_desc === 'Interconnect Fee (domestic)')
                                ${{ number_format($i->rate, 3, ".", ",") }}
                            @else
                                ${{ number_format($i->rate, 2, ".", ",") }}
                            @endif
                        </td>
						<td style="text-align: right;">{{ number_format($i->quantity, 2, ".", ",") }}</td>
						<td style="text-align: right;">${{ number_format($i->total, 2, ".", ",") }}</td>
					</tr>
					@php $amount += $i->total; @endphp
					@endforeach

					@if ($invoice->status != 'approved' && $invoice_desc !== null)
					{{ Form::open(array('route' => array('billing.invoice_add_item', $invoice->id), 'method' => 'post', 'autocomplete' => 'off')) }}
					<tr>
						<td>
							<select name="item_desc" class="form-control">
							@foreach ($invoice_desc as $id)
							<option value="{{ $id->id }}">
								{{ $id->item_desc }}
							</option>
							@endforeach
							</select>

							<br />

							<input type="text" class="form-control" name="item_desc_note" placeholder="Item Desc (optional)" />
						</td>
						<td style="text-align: right;">
							<input type="text" class="form-control" id="item_price" name="item_price" onKeyUp="updateTotals()" placeholder="0.00" />
						</td>
						<td style="text-align: right;">
							<input type="text" class="form-control" id="item_quantity" name="item_quantity" onKeyUp="updateTotals()" placeholder="0.00" />
						</td>
						<td style="text-align: right;">
							$<span id="item_total">0.00</span>

							<br /><br />

							<button class="btn btn-sm btn-success">add</button>
						</td>
					</tr>
					{{ Form::close() }}
					@endif

					<tr>
						<td colspan="2"></td>
						<td align="right">
							<b>Total</b>
						</td>
						<td align="right">
							<input type="hidden" name="grand_total_unformatted" id="grand_total_unformatted" value="{{ $amount }}" />
							$<span id="grand_total">{{ number_format($amount, 2, ".", ",") }}</span>
						</td>
					</tr>
				</tbody>
			</table>
		</td>
		<td width="20%" style="border-left: 2px solid #999999; padding-left: 15px;">
			<h4>TPV.com</h4><br />

			Our address:<br />
			3930 Commerce Avenue<br />
			Willow Grove, PA 19090

			<br /><br /><br />
			<hr />
			<br /><br />

			For questions on your invoice, please contact TPV.com Client Services at <a href="mailto:accountmanagers@answernet.com">accountmanagers@answernet.com</a>.
		</td>
	</tr>
</table>

<br />

<center>
	<strong>
		Remittance for Electronic Payments<br />
		Financial Institution: Firstrust Bank<br />
		15 E. Ridge Pike; Conshohocken, PA 19248<br />
		Account name: AnswerNet TPV<br />
		ABA number: 236073801<br />
		Account number: 8000335722<br />
	</strong>
</center>

<br />

<hr style="border-style: dashed;" />

<center>Please detach and return this remittance portion with your check.</center>

<br />

<table width="100%">
	<tr>
		<td>
			<img src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png" />
		</td>
		<td>
			<strong>TPV.com</strong><br />
			Attn: Accounts Receivable<br />
			3930 Commerce Avenue<br />
			Willow Grove, PA 19090
		</td>
		<td style="text-align: right;">
			Invoice Number: {{ $invoice->invoice_number }}<br />
            @if($invoice->purchase_order_no !== null)
                PO # {{ $invoice->purchase_order_no}} <br />
            @endif
			Bill Date: {{ date("F j, Y", strtotime($invoice->invoice_bill_date)) }}<br />
			Due Date: {{ date("F j, Y", strtotime($invoice->invoice_due_date)) }}<br />
			Amount Due: $<span id="amount_bottom">{{ number_format($amount, 2, ".", ",") }}</span><br /><br />
		</td>
	</tr>
</table>

</body>
</html>
