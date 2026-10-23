<?php
/**
 * Nunno WhatsApp Outreach & Campaign Hub
 * Configuration & System Blueprint
 */

return [
    'app_name'        => 'Nunno WhatsApp Outreach Hub',
    'app_tagline'     => 'Compliant WhatsApp Campaign & Audience Outreach Studio',
    'app_version'     => '2.0.0',
    'platform_url'    => 'https://nunno.cloud',
    'marketing_url'   => '../nunno_marketing/index.php',
    'crm_url'         => '../nunno_sales/dashboard.php',
    'support_email'   => 'numeniousnexus@gmail.com',
    'official_wa'     => '+923000000000',

    // Direct Connected WhatsApp Sender (Direct device/web connection — No WhatsApp API)
    // User will provide their WhatsApp number later (e.g. +923001234567)
    'connected_whatsapp_number' => '',
    'sender_engine' => [
        'mode'               => 'direct_device', // Direct WhatsApp Sender (No Meta Cloud API)
        'anti_ban_delay_min' => 4,               // Anti-ban delay min (seconds)
        'anti_ban_delay_max' => 8,               // Anti-ban delay max (seconds)
    ],

    // Nunno Platform Plans
    'plans' => [
        'starter' => [
            'id'       => 'starter',
            'name'     => 'Nunno Starter',
            'monthly'  => 1700,
            'annual'   => 17000,
            'currency' => 'PKR',
            'usdt'     => 6,
            'badge'    => 'Scalper Favorite',
            'pitch'    => 'Liquidation Magnet Indicator + 50 AI Market Chats/day',
            'url'      => 'https://nunno.cloud/starter'
        ],
        'pro' => [
            'id'       => 'pro',
            'name'     => 'Nunno Pro',
            'monthly'  => 5000,
            'annual'   => 50000,
            'currency' => 'PKR',
            'usdt'     => 18,
            'badge'    => 'Most Popular ⭐',
            'pitch'    => '12-Layer Full Comp Market Report + Live Multi-Symbol Breakout Alerts',
            'url'      => 'https://nunno.cloud/pro'
        ],
        'whale' => [
            'id'       => 'whale',
            'name'     => 'Nunno Whale VIP',
            'monthly'  => 15000,
            'annual'   => 150000,
            'currency' => 'PKR',
            'usdt'     => 55,
            'badge'    => 'Institutional Alpha',
            'pitch'    => 'Private Whale Order Flow + 1-on-1 Trading Architect Access',
            'url'      => 'https://nunno.cloud/whale'
        ]
    ],

    // Pakistani Mobile Networks Prefix Map
    'pakistan_networks' => [
        'Jazz'    => ['0300', '0301', '0302', '0303', '0304', '0305', '0306', '0307', '0308', '0309', '0320', '0321', '0322', '0323', '0324', '0325', '0326', '0327', '0328', '0329'],
        'Telenor' => ['0340', '0341', '0342', '0343', '0344', '0345', '0346', '0347', '0348', '0349'],
        'Zong'    => ['0310', '0311', '0312', '0313', '0314', '0315', '0316', '0317', '0318'],
        'Ufone'   => ['0330', '0331', '0332', '0333', '0334', '0335', '0336', '0337'],
        'SCOM'    => ['0355']
    ],

    // High-Converting Outreach Templates
    'default_templates' => [
        [
            'id'       => 'pro_liquidation_urdu',
            'name'     => 'Crypto Liquidation Alert (Roman Urdu)',
            'category' => 'High Intent Crypto',
            'lang'     => 'Roman Urdu',
            'body'     => "{Salam|AoA|Hello} {name|Bhai|Trader}!\n\nKya aap bar bar market ke sudden wick ya stop-loss hunt se liquid ho rahe hain? 📉❌\n\n*Nunno AI Cloud* (`nunno.cloud`) ne Pakistan ke top traders ke liye *Liquidation Magnet System* launch kiya hai jo exact level pehle dikhata hai jahan whales retail ko target kar rahi hain.\n\n🔥 *Special Community Offer:*\n• {plan|Nunno Pro} sirf *Rs. {price|5,000}/month*\n• EasyPaisa, JazzCash aur USDT accepted\n• Coupon `{coupon|NUNNO30}` se *30% OFF*\n\nLive Demo aur Signals dekhne ke liye visit karein: {link|https://nunno.cloud}\n\n_Reply STOP agar aap mazeed updates nahi chahte._",
        ],
        [
            'id'       => 'gold_scalper_hybrid',
            'name'     => 'Gold (XAUUSD) & Forex Scalper Pitch',
            'category' => 'Forex & Commodities',
            'lang'     => 'Hybrid Urdu/English',
            'body'     => "{Salam|Hello} {name|Trader}!\n\nAgar aap Gold ($XAUUSD) ya Forex trade karte hain, toh US Session wicks aur manipulated breakouts se safe rehne ka solution ab available hai.\n\n⚡ *Nunno Cloud 12-Layer Market Engine:*\n1. Real-time institutional order flow alerts\n2. London & New York session liquidation heatmaps\n3. 92% accurate reversal signals\n\nAccess {plan|Nunno Pro} starting at just Rs. {price|1,700}/mo.\n\nCheck live platform: {link|https://nunno.cloud}\nCoupon Code: *{coupon|GOLDPRO}*\n\n_Agar aap message nahi chahte toh reply STOP karein._",
        ],
        [
            'id'       => 'flash_discount_easypaisa',
            'name'     => 'Flash 48-Hour Discount (EasyPaisa/JazzCash)',
            'category' => 'Promotional Flash',
            'lang'     => 'Roman Urdu',
            'body'     => "⚡ *FLASH 48-HOUR TRADER PASS — NUNNO CLOUD* ⚡\n\n{Salam|AoA} {name|Dost},\n\nSirf agle 48 ghante ke liye, Nunno AI Trading Suite pe flat *{discount|40% OFF}* offer chal rahi hai!\n\n✅ Whales Liquidation Radar\n✅ Live Breakout Tracker\n✅ Urdu Video Guides & VIP Community\n\n💳 Instant payment via *JazzCash / EasyPaisa / Binance Pay*\n\nApna discount claim karein coupon code *{coupon|FLASH40}* ke sath:\n👉 {link|https://nunno.cloud}\n\n_Reply STOP to unsubscribe._",
        ],
        [
            'id'       => 'institutional_alpha_en',
            'name'     => 'Institutional Alpha (English Professional)',
            'category' => 'VIP & Institutional',
            'lang'     => 'English',
            'body'     => "Hi {name|Trader},\n\nAre institutional liquidation wicks costing you profitability in your crypto positions?\n\n*Nunno Cloud* provides predictive order-flow analytics, institutional volume heatmaps, and AI-driven breakout filters designed for serious traders.\n\nKey Highlights for {city|Pakistan} Traders:\n• 12-Layer Liquidation Heatmap\n• Multi-symbol breakout telemetry\n• Localized PKR & Crypto gateway\n\nClaim your {discount|25%} discount on {plan|Nunno Pro} with code *{coupon|ALPHA25}*:\n🔗 {link|https://nunno.cloud}\n\n_Reply STOP to opt out._",
        ]
    ]
];
