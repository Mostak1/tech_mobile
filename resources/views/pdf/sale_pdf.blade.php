@php
    $pdfLocale = app()->getLocale();
    $isRtl = $pdfLocale === 'ar';
    $rtlLabelSuffix = $isRtl ? '' : ':';

    $priceFormat = $setting['price_format'] ?? null;
    if (!function_exists('formatPrice')) {
        function formatPrice($number, $decimals = 2, $priceFormat = null) {
            $number = (float) $number;
            $decimals = (int) $decimals;

            if (empty($priceFormat)) {
                return number_format($number, $decimals, '.', ',');
            }

            switch ($priceFormat) {
                case 'comma_dot':
                    return number_format($number, $decimals, '.', ',');
                case 'dot_comma':
                    return number_format($number, $decimals, ',', '.');
                case 'space_comma':
                    return number_format($number, $decimals, ',', ' ');
                default:
                    return number_format($number, $decimals, '.', ',');
            }
        }
    }

    if (!function_exists('numToWords')) {
        function numToWords($number) {
            $number = round((float)$number, 2);
            $whole = floor($number);
            $fraction = round(($number - $whole) * 100);

            $words = array(
                0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
                5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
                10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
                14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
                18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
                40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
                80 => 'Eighty', 90 => 'Ninety'
            );

            $convertGroup = function($n) use ($words) {
                $str = '';
                if ($n >= 100) {
                    $str .= $words[floor($n / 100)] . ' Hundred ';
                    $n %= 100;
                }
                if ($n > 0) {
                    if ($n < 20) {
                        $str .= $words[$n] . ' ';
                    } else {
                        $str .= $words[floor($n / 10) * 10] . ' ';
                        if ($n % 10 > 0) {
                            $str .= $words[$n % 10] . ' ';
                        }
                    }
                }
                return $str;
            };

            if ($whole == 0) {
                $wStr = 'Zero';
            } else {
                $wStr = '';
                if ($whole >= 10000000) {
                    $wStr .= $convertGroup(floor($whole / 10000000)) . 'Crore ';
                    $whole %= 10000000;
                }
                if ($whole >= 100000) {
                    $wStr .= $convertGroup(floor($whole / 100000)) . 'Lakh ';
                    $whole %= 100000;
                }
                if ($whole >= 1000) {
                    $wStr .= $convertGroup(floor($whole / 1000)) . 'Thousand ';
                    $whole %= 1000;
                }
                if ($whole > 0) {
                    $wStr .= $convertGroup($whole);
                }
            }

            $res = trim($wStr) . ' Taka';
            if ($fraction > 0) {
                $res .= ' and ' . trim($convertGroup($fraction)) . ' Paisa';
            }
            return $res . ' Only';
        }
    }

    // Determine payment method for checkbox state
    $pmName = 'Cash';
    if (isset($payments) && count($payments) > 0 && isset($payments[0]->payment_method)) {
        $pmName = $payments[0]->payment_method->name ?? 'Cash';
    }
    $pmLower = strtolower($pmName);
    $isCash = strpos($pmLower, 'cash') !== false;
    $isMfs = strpos($pmLower, 'bkash') !== false || strpos($pmLower, 'nagad') !== false || strpos($pmLower, 'mfs') !== false || strpos($pmLower, 'rocket') !== false;
    $isCheque = strpos($pmLower, 'cheque') !== false || strpos($pmLower, 'check') !== false;
    $isCard = strpos($pmLower, 'card') !== false || strpos($pmLower, 'debit') !== false || strpos($pmLower, 'credit') !== false;
    $isBank = strpos($pmLower, 'bank') !== false || strpos($pmLower, 'transfer') !== false;
    $isCod = strpos($pmLower, 'cod') !== false || strpos($pmLower, 'delivery') !== false;

    // Fallback if none checked
    if (!$isCash && !$isMfs && !$isCheque && !$isCard && !$isBank && !$isCod) {
        $isCash = true;
    }

    $logoSrc = null;
    if (!empty($setting['logo'])) {
        if (!empty($isInlineHtml) || request()->is('*sale_print_html*')) {
            $logoSrc = asset('images/'.$setting['logo']);
        } else {
            $logoPath = public_path('images/'.$setting['logo']);
            if (file_exists($logoPath) && is_readable($logoPath)) {
                $logoData = @file_get_contents($logoPath);
                if ($logoData !== false) {
                    $logoB64 = base64_encode($logoData);
                    $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                    $logoMime = $logoExt === 'svg' ? 'image/svg+xml' : (in_array($logoExt, ['png','jpeg','jpg','gif','webp'], true) ? 'image/'.$logoExt : 'image/png');
                    if ($logoExt === 'jpg') { $logoMime = 'image/jpeg'; }
                    $logoSrc = 'data:'.$logoMime.';base64,'.$logoB64;
                }
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Invoice - {{$sale['Ref']}}</title>
    <style>
        @page { 
            size: A4 portrait;
            margin: 0; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, 'DejaVu Sans' !important;
        }
        body { 
            font-size: 9.5pt; 
            color: #111111; 
            line-height: 1.35; 
            background: #ffffff;
            position: relative;
            padding: 0;
        }
        
        .page-container {
            padding: 25px 35px 25px 35px;
        }

        /* Contacts Icon circles */
        .icon-box {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: #f25822;
            color: #ffffff;
            border-radius: 50%;
            text-align: center;
            line-height: 16px;
            font-size: 8pt;
            font-weight: bold;
            margin-right: 6px;
        }

        .company-name {
            font-size: 16pt;
            font-weight: 900;
            color: #f25822;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .company-tagline {
            font-size: 9.5pt;
            font-style: italic;
            color: #2e7d32;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .invoice-title {
            font-size: 28pt;
            font-weight: 900;
            color: #111111;
            letter-spacing: 1px;
            text-align: right;
            margin-bottom: 8px;
        }

        /* Product Table styling */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .items-table th {
            background-color: #f25822;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #f25822;
        }
        .items-table td {
            border: 1px solid #f25822;
            padding: 8px 8px;
            font-size: 9pt;
            vertical-align: middle;
        }

        .checkbox-item {
            display: inline-block;
            margin-right: 12px;
            font-size: 9.5pt;
            font-weight: 600;
        }
        .checkbox-box {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.5px solid #333333;
            text-align: center;
            line-height: 11px;
            font-size: 8pt;
            margin-right: 4px;
            vertical-align: middle;
        }
        .checkbox-box.checked {
            background-color: #333333;
            color: #ffffff;
        }

        .total-pill {
            background-color: #f25822;
            color: #ffffff;
            font-size: 13pt;
            font-weight: bold;
            padding: 6px 14px;
            border-radius: 4px;
            display: inline-block;
            text-align: center;
        }

        /* Center Page Watermark Logo */
        .watermark-container {
            position: absolute;
            top: 42%;
            left: 50%;
            margin-left: -175px;
            margin-top: -120px;
            width: 350px;
            text-align: center;
            opacity: 0.08;
            z-index: -10;
        }
        .watermark-container img {
            max-width: 350px;
            max-height: 300px;
            width: auto;
            height: auto;
        }
    </style>
</head>
<body>
    @if($logoSrc)
        <!-- Middle Page Watermark Logo -->
        <div class="watermark-container">
            <img src="{{ $logoSrc }}" alt="Watermark Logo">
        </div>
    @endif

    <div class="page-container">
        <!-- Header Section -->
        <table style="width: 100%; margin-bottom: 10px;" cellpadding="0" cellspacing="0">
            <tr>
                <!-- Left Column: Logo & Company Details -->
                <td style="width: 58%; vertical-align: top;">
                    @if($logoSrc)
                        <div style="margin-bottom: 6px;">
                            <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 65px; max-width: 200px;">
                        </div>
                    @endif

                    <div class="company-name">{{ $setting['CompanyName'] ?? 'RAJON INTERNATIONAL' }}</div>
                    <div class="company-tagline">Blending life with smartphone!</div>

                    <table style="width: 100%; font-size: 8.5pt; color: #0b497b;" cellpadding="2" cellspacing="0">
                        @if(!empty($setting['email']))
                        <tr>
                            <td style="width: 18px; vertical-align: middle;"><span class="icon-box">W</span></td>
                            <td style="vertical-align: middle; color: #0b497b; font-weight: 500;">www.{{ strtolower(preg_replace('/^https?:\/\//', '', $setting['email'])) }}</td>
                        </tr>
                        <tr>
                            <td style="width: 18px; vertical-align: middle;"><span class="icon-box">@</span></td>
                            <td style="vertical-align: middle; color: #0b497b; font-weight: 500;">{{ $setting['email'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($setting['facebook']))
                        <tr>
                            <td style="width: 18px; vertical-align: middle;"><span class="icon-box">f</span></td>
                            <td style="vertical-align: middle; color: #0b497b; font-weight: 500;">{{ $setting['facebook'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($setting['CompanyPhone']))
                        <tr>
                            <td style="width: 18px; vertical-align: middle;"><span class="icon-box">P</span></td>
                            <td style="vertical-align: middle; color: #0b497b; font-weight: bold;">{{ $setting['CompanyPhone'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($setting['CompanyAdress']))
                        <tr>
                            <td style="width: 18px; vertical-align: top;"><span class="icon-box">A</span></td>
                            <td style="vertical-align: top; color: #0b497b; font-weight: 500;">{{ $setting['CompanyAdress'] }}</td>
                        </tr>
                        @endif
                    </table>
                </td>

                <!-- Right Column: Invoice Title & Meta -->
                <td style="width: 42%; vertical-align: top; text-align: right;">
                    <div class="invoice-title">INVOICE</div>

                    <table style="width: 100%; font-size: 10.5pt; font-weight: bold;" cellpadding="4" cellspacing="0">
                        <tr>
                            <td style="text-align: right; color: #111111; width: 45%;">Invoice No:</td>
                            <td style="text-align: left; padding-left: 10px; color: #111111; width: 55%;">{{ $sale['Ref'] }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; color: #111111;">Invoice Date:</td>
                            <td style="text-align: left; padding-left: 10px; color: #111111;">
                                @php
                                    $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                    $dateTime = \Carbon\Carbon::parse($sale['date']);
                                    $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                    $formattedDate = $dateTime->format($phpDateFormat);
                                @endphp
                                {{ $formattedDate }}
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align: right; color: #111111;">Due Date:</td>
                            <td style="text-align: left; padding-left: 10px; color: #111111;">{{ $formattedDate }}</td>
                        </tr>
                    </table>

                    <!-- Customer Information Details -->
                    <div style="margin-top: 25px; text-align: left;">
                        <table style="width: 100%; font-size: 10pt;" cellpadding="3" cellspacing="0">
                            <tr>
                                <td style="width: 75px; font-weight: 900; color: #111111;">NAME:</td>
                                <td style="font-weight: bold; color: #111111;">{{ $sale['client_name'] }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 900; color: #111111;">Phone:</td>
                                <td style="font-weight: 500; color: #111111;">{{ $sale['client_phone'] }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 900; color: #111111;">Email:</td>
                                <td style="font-weight: 500; color: #111111;">{{ $sale['client_email'] ?? '---' }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 900; color: #111111; vertical-align: top;">Address:</td>
                                <td style="font-weight: 500; color: #111111; vertical-align: top;">{{ $sale['client_adr'] ?? '---' }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Product Description Table -->
        @php
            $subtotal = 0;
            foreach ($details as $detail) {
                $subtotal += (float)$detail['total'];
            }
            $discountMethod = $sale['discount_Method'] ?? '2';
            $discountValue = (float)$sale['discount'];
            $manualDiscountAmount = $discountMethod === '1' ? $subtotal * ($discountValue / 100) : min($discountValue, $subtotal);
        @endphp

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">ITEM NO.</th>
                    <th style="width: 45%; text-align: left; padding-left: 10px;">PRODUCT DESCRIPTION</th>
                    <th style="width: 15%;">RATE</th>
                    <th style="width: 12%;">QUANTITY</th>
                    <th style="width: 18%;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                @php $itemNum = 1; @endphp
                @foreach ($details as $detail)
                <tr style="height: 38px;">
                    <td style="text-align: center; font-weight: bold;">{{ sprintf('%02d', $itemNum) }}</td>
                    <td style="text-align: left; padding-left: 10px;">
                        <div style="font-weight: bold; color: #111111;">{{ $detail['name'] }}</div>
                        @if($detail['is_imei'] && !empty($detail['imei_number']))
                            <div style="font-size: 8pt; color: #d84315; font-weight: bold; margin-top: 1px;">S/N: {{ $detail['imei_number'] }}</div>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ formatPrice((float)$detail['price'], 2, $priceFormat) }}</td>
                    <td style="text-align: center;">{{ $detail['quantity'] }}</td>
                    <td style="text-align: right; padding-right: 10px; font-weight: bold;">{{ formatPrice((float)$detail['total'], 2, $priceFormat) }}</td>
                </tr>
                @php $itemNum++; @endphp
                @endforeach

                {{-- Fill empty rows up to minimum 5 rows for standard invoice height --}}
                @for ($i = $itemNum; $i <= 5; $i++)
                <tr style="height: 38px;">
                    <td style="text-align: center;">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                @endfor
            </tbody>
        </table>

        <!-- Summary & Payment Section -->
        <table style="width: 100%; margin-top: 10px;" cellpadding="0" cellspacing="0">
            <tr>
                <!-- Left: Payment Methods & Amount in Words -->
                <td style="width: 60%; vertical-align: top;">
                    <div style="font-size: 9.5pt; font-weight: bold; color: #111111; margin-bottom: 5px;">
                        Payment Details:
                    </div>
                    @if(isset($payments) && count($payments) > 0)
                    <table style="width: 95%; border-collapse: collapse; border: 1px solid #f25822; margin-bottom: 10px;" cellpadding="3" cellspacing="0">
                        <thead>
                            <tr style="background: #f25822; color: #ffffff; font-size: 8pt; font-weight: bold;">
                                <th style="padding: 4px; text-align: left; border-right: 1px solid #ffffff;">Date</th>
                                <th style="padding: 4px; text-align: left; border-right: 1px solid #ffffff;">Payment REF</th>
                                <th style="padding: 4px; text-align: left; border-right: 1px solid #ffffff;">Method</th>
                                <th style="padding: 4px; text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $p)
                            <tr style="font-size: 8.5pt; border-bottom: 1px solid #f25822;">
                                <td style="padding: 4px; color: #111111; border-right: 1px solid #f25822;">{{ $p->date }}</td>
                                <td style="padding: 4px; color: #111111; border-right: 1px solid #f25822;">{{ $p->Ref }}</td>
                                <td style="padding: 4px; color: #111111; font-weight: bold; border-right: 1px solid #f25822;">{{ $p->payment_method ? $p->payment_method->name : 'Cash' }}</td>
                                <td style="padding: 4px; text-align: right; font-weight: bold; color: #111111;">{{ $symbol }} {{ formatPrice($p->montant, 2, $priceFormat) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div style="font-size: 8.5pt; color: #777777; font-style: italic; margin-bottom: 10px;">
                        No payment records (Unpaid)
                    </div>
                    @endif

                    <div style="font-size: 9.5pt; font-weight: bold; color: #111111; margin-top: 15px; line-height: 1.8;">
                        Amount in Words: <span style="font-weight: bold; font-style: italic; border-bottom: 1px stroke #111;">{{ numToWords($sale['GrandTotal']) }}</span>
                    </div>
                </td>

                <!-- Right: Sub-total, Discount, Due, Total Pill -->
                <td style="width: 40%; vertical-align: top; text-align: right;">
                    <table style="width: 100%; font-size: 11pt; font-weight: bold;" cellpadding="4" cellspacing="0">
                        <tr>
                            <td style="text-align: right; color: #111111; width: 50%;">Sub-total :</td>
                            <td style="text-align: right; color: #111111; width: 50%;">{{ $symbol }} {{ formatPrice($subtotal, 2, $priceFormat) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; color: #111111;">Discount :</td>
                            <td style="text-align: right; color: #111111;">{{ $symbol }} {{ formatPrice($manualDiscountAmount, 2, $priceFormat) }}</td>
                        </tr>
                        @if((float)$sale['due'] > 0)
                        <tr>
                            <td style="text-align: right; color: #f25822;">Due (if any) :</td>
                            <td style="text-align: right; color: #f25822;">{{ $symbol }} {{ formatPrice((float)$sale['due'], 2, $priceFormat) }}</td>
                        </tr>
                        @else
                        <tr>
                            <td style="text-align: right; color: #111111;">Due (if any) :</td>
                            <td style="text-align: right; color: #111111;">{{ $symbol }} 0.00</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="2" style="padding-top: 8px; text-align: right;">
                                <div class="total-pill" style="width: 100%;">
                                    <table style="width: 100%; color: #ffffff; font-weight: bold;" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="text-align: left; font-size: 13pt;">Total :</td>
                                            <td style="text-align: right; font-size: 13pt;">{{ $symbol }} {{ formatPrice((float)$sale['GrandTotal'], 2, $priceFormat) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Signatures & Footnote -->
        <table style="width: 100%; margin-top: 45px;" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 45%; text-align: center; vertical-align: bottom;">
                    <div style="border-top: 1.5px solid #111111; width: 85%; margin: 0 auto 5px auto;"></div>
                    <div style="font-size: 9.5pt; font-weight: bold; color: #111111; text-transform: uppercase;">CUSTOMER'S SIGNATURE</div>
                </td>
                <td style="width: 10%;"></td>
                <td style="width: 45%; text-align: center; vertical-align: bottom;">
                    <div style="border-top: 1.5px solid #111111; width: 85%; margin: 0 auto 5px auto;"></div>
                    <div style="font-size: 9.5pt; font-weight: bold; color: #111111; text-transform: uppercase;">AUTHORISED SIGNATURE</div>
                </td>
            </tr>
        </table>

        @if(!empty($setting->is_invoice_footer) && !empty($setting->invoice_footer))
            <div style="text-align: center; margin-top: 15px; font-size: 8.5pt; font-weight: bold; color: #111111;">
                {!! nl2br(e($setting->invoice_footer)) !!}
            </div>
        @else
            <div style="text-align: center; margin-top: 15px; font-size: 8.5pt; font-weight: bold; color: #111111;">
                ***Please refer to the reverse side of this invoice for our terms and conditions***
            </div>
        @endif
    </div>
</body>
</html>
