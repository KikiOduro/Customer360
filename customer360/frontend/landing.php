<?php
/**
 * Customer 360 - Landing Page
 * Analytics for Ghanaian SMEs
 */

// You can add PHP logic here (e.g., session handling, dynamic content)
$currentYear = date('Y');
$trustedBusinesses = "500+";
$contactEmail = "support@customer360.gh";
$contactPhone = "+233 20 000 0000";
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - Analytics for Ghanaian SMEs</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&family=Noto+Sans:wght@400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0b203c",
                        "primary-hover": "#153055",
                        "accent-gold": "#D4AF37",
                        "accent-emerald": "#10b981",
                        "background-light": "#f8fafb",
                        "background-dark": "#121820",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "body": ["Noto Sans", "sans-serif"],
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
<div class="relative flex h-auto min-h-screen w-full flex-col bg-background-light dark:bg-background-dark group/design-root overflow-x-hidden">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-[#f8fafb]/90 backdrop-blur-md border-b border-solid border-b-[#e8ecf2]">
        <div class="layout-container flex h-full grow flex-col">
            <div class="flex flex-1 justify-center">
                <div class="layout-content-container flex flex-col max-w-[1200px] flex-1">
                    <div class="flex items-center justify-between whitespace-nowrap px-4 md:px-10 py-4">
                        <div class="flex items-center gap-3 text-[#0f141a]">
                            <div class="size-8 text-primary">
                                <span class="material-symbols-outlined text-3xl">analytics</span>
                            </div>
                            <h2 class="text-primary text-xl font-bold leading-tight tracking-[-0.015em]">Customer 360</h2>
                        </div>
                        <div class="hidden md:flex flex-1 justify-end gap-8 items-center">
                            <div class="flex items-center gap-8">
                                <a class="text-primary/80 hover:text-primary text-sm font-medium leading-normal transition-colors" href="#features">Features</a>
                                <a class="text-primary/80 hover:text-primary text-sm font-medium leading-normal transition-colors" href="#how-it-works">How it works</a>
                                <a class="text-primary/80 hover:text-primary text-sm font-medium leading-normal transition-colors" href="#pricing">Pricing</a>
                                <a class="text-primary/80 hover:text-primary text-sm font-medium leading-normal transition-colors" href="#faq">FAQ</a>
                            </div>
                            <a href="index.html" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-6 bg-primary hover:bg-primary-hover text-[#f8fafb] text-sm font-bold leading-normal tracking-[0.015em] transition-all">
                                <span class="truncate">Get Started</span>
                            </a>
                        </div>
                        <!-- Mobile Menu Icon -->
                        <div class="md:hidden text-primary">
                            <span class="material-symbols-outlined">menu</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="layout-container flex h-full grow flex-col">
        <div class="flex flex-1 justify-center py-5">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1">
                <div class="@container">
                    <div class="flex flex-col gap-10 px-4 py-10 lg:py-20 @[864px]:flex-row items-center">
                        <!-- Text Content -->
                        <div class="flex flex-col gap-8 @[480px]:min-w-[400px] @[864px]:w-1/2 justify-center">
                            <div class="flex flex-col gap-4 text-left">
                                <h1 class="text-primary text-4xl font-black leading-tight tracking-[-0.033em] @[480px]:text-5xl lg:text-6xl">
                                    Know Your Best Customers. <span class="text-accent-gold">Grow Your Business.</span>
                                </h1>
                                <h2 class="text-[#3b4b5c] text-lg font-normal leading-relaxed max-w-lg">
                                    The premier data analytics tool for Ghanaian SMEs. Turn your sales data into actionable insights in minutes.
                                </h2>
                            </div>
                            <div class="flex flex-wrap gap-4">
                                <a href="index.html#upload" class="flex min-w-[140px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-6 bg-accent-gold hover:bg-[#c29f2d] text-primary text-base font-bold leading-normal tracking-[0.015em] shadow-md transition-all">
                                    <span class="truncate">Upload Data</span>
                                    <span class="material-symbols-outlined ml-2 text-sm">upload</span>
                                </a>
                                <button class="flex min-w-[140px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-6 bg-transparent border-2 border-primary text-primary hover:bg-primary/5 text-base font-bold leading-normal tracking-[0.015em] transition-all">
                                    <span class="truncate">View Demo</span>
                                </button>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#536e93] mt-2">
                                <span class="material-symbols-outlined text-accent-emerald text-lg">check_circle</span>
                                <span>Trusted by <?php echo $trustedBusinesses; ?> businesses in Accra &amp; Kumasi</span>
                            </div>
                        </div>
                        <!-- Image/Visual -->
                        <div class="w-full @[864px]:w-1/2">
                            <div class="rounded-xl shadow-2xl border border-[#d1dae5] overflow-hidden bg-white aspect-[4/3] relative group">
                                <div class="w-full h-full bg-cover bg-top" data-alt="Modern data analytics dashboard showing sales growth charts and customer tables" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDSylwvCtm_fnHZrs_L_s0biNyYbL7k-iVwv4z3qqMqY-kzLBB3OTX9q2IwzVFZTc4MKjT4-eLuJN34MQM2WqSlVN593SnxBHh-keGJneOqoAKr9nagkeNuqWQAwf9E9eqPEO2Ntq-RqA-_WSQ-iTyt8fm7kt4arwvsagSHcgXTrWl9DE2t9PYrLHdk09yFjab4WLOzQ1g0nVw2-SHAsoyhqhyq0wjnwClTDL5VTaVjq0rZpOwLZji7CjFMuFTVWPMg7RwOjY7J6vQz');"></div>
                                <!-- Overlay to make it look more like a specific app mockup -->
                                <div class="absolute inset-0 bg-gradient-to-tr from-primary/10 to-transparent pointer-events-none"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trust / Features Summary Section -->
    <div class="bg-white border-y border-[#e8ecf2]">
        <div class="layout-container flex justify-center">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4 py-16">
                <div class="flex flex-col gap-10">
                    <div class="text-center">
                        <h2 class="text-primary text-3xl font-bold leading-tight mb-2">Built for SMEs in Ghana</h2>
                        <p class="text-[#536e93]">Simple, powerful tools designed for the local market.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Card 1 -->
                        <div class="flex flex-col items-center text-center gap-4 p-6 rounded-xl bg-[#f8fafb] border border-[#e8ecf2] hover:shadow-lg transition-shadow">
                            <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-2">
                                <span class="material-symbols-outlined text-3xl">shield</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <h3 class="text-primary text-xl font-bold">Security</h3>
                                <p class="text-[#536e93] text-sm leading-relaxed">Bank-grade data protection ensures your business records stay private and secure.</p>
                            </div>
                        </div>
                        <!-- Card 2 -->
                        <div class="flex flex-col items-center text-center gap-4 p-6 rounded-xl bg-[#f8fafb] border border-[#e8ecf2] hover:shadow-lg transition-shadow">
                            <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-2">
                                <span class="material-symbols-outlined text-3xl">bolt</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <h3 class="text-primary text-xl font-bold">Speed</h3>
                                <p class="text-[#536e93] text-sm leading-relaxed">Instant analysis. Upload your sales sheet and get actionable reports in seconds.</p>
                            </div>
                        </div>
                        <!-- Card 3 -->
                        <div class="flex flex-col items-center text-center gap-4 p-6 rounded-xl bg-[#f8fafb] border border-[#e8ecf2] hover:shadow-lg transition-shadow">
                            <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-2">
                                <span class="material-symbols-outlined text-3xl">sentiment_satisfied</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <h3 class="text-primary text-xl font-bold">Simplicity</h3>
                                <p class="text-[#536e93] text-sm leading-relaxed">No technical skills required. Designed with an easy-to-use interface for everyone.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Features Section -->
    <div class="layout-container flex justify-center py-20 bg-[#f8fafb]" id="features">
        <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4">
            <div class="mb-12 text-center md:text-left">
                <span class="text-accent-emerald font-bold tracking-wider text-sm uppercase mb-2 block">Growth Tools</span>
                <h2 class="text-primary text-3xl md:text-4xl font-bold leading-tight">Powerful Features for Growth</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">groups</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">Customer Segmentation</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Automatically group your customers by buying behavior to target them better.</p>
                    </div>
                </div>
                <!-- Feature 2 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">trending_up</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">Sales Forecasting</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Predict next month's revenue based on historical data trends.</p>
                    </div>
                </div>
                <!-- Feature 3 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">Inventory Optimization</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Know exactly what to restock and when, reducing dead stock.</p>
                    </div>
                </div>
                <!-- Feature 4 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">loyalty</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">Loyalty Tracking</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Identify your VIP spenders and reward them to keep them coming back.</p>
                    </div>
                </div>
                <!-- Feature 5 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">sms</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">SMS Integration</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Send targeted offers directly to customer phones from the dashboard.</p>
                    </div>
                </div>
                <!-- Feature 6 -->
                <div class="group flex gap-4 rounded-xl border border-[#d1dae5] bg-white p-6 hover:border-accent-emerald transition-colors shadow-sm">
                    <div class="text-accent-emerald shrink-0 bg-emerald-50 p-3 rounded-lg h-fit group-hover:bg-emerald-100 transition-colors">
                        <span class="material-symbols-outlined">file_download</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h3 class="text-primary text-lg font-bold leading-tight">Exportable Reports</h3>
                        <p class="text-[#536e93] text-sm leading-normal">Download professional PDF or CSV reports for your partners or bank.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="bg-primary text-white py-20" id="how-it-works">
        <div class="layout-container flex justify-center">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold mb-4">How It Works</h2>
                    <p class="text-white/70 max-w-2xl mx-auto">Get started in minutes. No complex setup or installation required.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10 relative">
                    <!-- Connecting Line (Desktop) -->
                    <div class="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-white/20 -z-0"></div>
                    <!-- Step 1 -->
                    <div class="relative z-10 flex flex-col items-center text-center gap-6">
                        <div class="size-24 rounded-full bg-accent-gold border-4 border-primary flex items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-primary text-4xl">upload_file</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold mb-2">1. Upload Data</h3>
                            <p class="text-white/70 text-sm px-4">Simply upload your Excel or CSV sales records into the secure dashboard.</p>
                        </div>
                    </div>
                    <!-- Step 2 -->
                    <div class="relative z-10 flex flex-col items-center text-center gap-6">
                        <div class="size-24 rounded-full bg-white border-4 border-primary flex items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-primary text-4xl">analytics</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold mb-2">2. AI Analysis</h3>
                            <p class="text-white/70 text-sm px-4">Our engine cleans your data and identifies patterns, trends, and customer segments.</p>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div class="relative z-10 flex flex-col items-center text-center gap-6">
                        <div class="size-24 rounded-full bg-white border-4 border-primary flex items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-primary text-4xl">rocket_launch</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold mb-2">3. Get Insights</h3>
                            <p class="text-white/70 text-sm px-4">Receive a comprehensive growth strategy report and start optimizing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials -->
    <div class="py-20 bg-background-light">
        <div class="layout-container flex justify-center">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4">
                <h2 class="text-primary text-3xl font-bold text-center mb-12">Success Stories</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Testimonial 1 -->
                    <div class="p-8 bg-white rounded-xl shadow-sm border border-[#e8ecf2] flex flex-col gap-4">
                        <div class="flex text-accent-gold gap-1">
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                        </div>
                        <p class="text-[#3b4b5c] italic text-lg leading-relaxed">"Customer 360 helped me identify my VIP clients instantly. I started a loyalty program for them, and my repeat sales have grown by 40% in just three months."</p>
                        <div class="flex items-center gap-4 mt-auto pt-4 border-t border-[#f1f4f8]">
                            <div class="size-12 rounded-full overflow-hidden bg-gray-200">
                                <div class="w-full h-full bg-cover bg-center" data-alt="Portrait of Ama, a Ghanaian fashion boutique owner smiling" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAYrt8LJQcpUv2i7H5QvPORanvJztNYxFS6NT74EchfHdgSybqLyKb13X5F0gViBIl1GnPH76vgA01CwGDsBLjQZBFj13LuK3RjBNXHpkCfPFUe2HEpJmpTry-VsNIQNzp8LoxTKk3GNtx4Dd_fdffdDjGwIBcnE-XhB-y9aKYAOKGe_3QcHByD121CaQOz2tvXMfRN839xIWkOHfb_yLQBG_QUwxucDRA73y2vqkAa0Jm-0SfoFC1ZkuubdBx9BOgRtK1146cp8ilo');"></div>
                            </div>
                            <div>
                                <p class="text-primary font-bold">Ama O.</p>
                                <p class="text-xs text-[#536e93] uppercase tracking-wide">Fashion Boutique Owner, Accra</p>
                            </div>
                        </div>
                    </div>
                    <!-- Testimonial 2 -->
                    <div class="p-8 bg-white rounded-xl shadow-sm border border-[#e8ecf2] flex flex-col gap-4">
                        <div class="flex text-accent-gold gap-1">
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                            <span class="material-symbols-outlined text-sm">star</span>
                        </div>
                        <p class="text-[#3b4b5c] italic text-lg leading-relaxed">"Managing inventory for my electronics shop was a headache. This tool showed me exactly which items move fast. It's built perfectly for how we do business in Ghana."</p>
                        <div class="flex items-center gap-4 mt-auto pt-4 border-t border-[#f1f4f8]">
                            <div class="size-12 rounded-full overflow-hidden bg-gray-200">
                                <div class="w-full h-full bg-cover bg-center" data-alt="Portrait of Kwame, an electronics shop owner" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDOsMWU0P3xHGDLAscQlwJGORKZe61lR6CEeIHl_84paDAib8dhovq_1LD07wzma1-0qvWuR9iioS-H7SSljF0Dmz76UtYCuBd730RdBGpJeKJS05a86Kn6bMI6CLaowBtU98KA9NemipwIZCBL-5QC4ww4wc3pt7y2_bb8bV-13pQkto5qoLwBgHJu0H6ANPJjv24uAjuRjyvX4fS6lW6G_uSHgkabL8vlGgGUqjaETb1thmROcoxzpHJrfoRZ2lNNbAntGmeh7j4s');"></div>
                            </div>
                            <div>
                                <p class="text-primary font-bold">Kwame M.</p>
                                <p class="text-xs text-[#536e93] uppercase tracking-wide">Electronics Dealer, Kumasi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div class="py-20 bg-white border-t border-[#e8ecf2]" id="pricing">
        <div class="layout-container flex justify-center">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4">
                <div class="text-center mb-16">
                    <h2 class="text-primary text-3xl md:text-4xl font-bold mb-4">Transparent Pricing</h2>
                    <p class="text-[#536e93] max-w-2xl mx-auto">Choose a plan that fits your business stage.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
                    <!-- Starter Plan -->
                    <div class="flex flex-col p-6 rounded-2xl border border-[#d1dae5] bg-white h-full hover:border-primary/30 transition-colors">
                        <h3 class="text-primary text-xl font-bold mb-2">Starter</h3>
                        <div class="mb-4">
                            <span class="text-4xl font-black text-primary">GH₵ 150</span>
                            <span class="text-[#536e93] text-sm">/month</span>
                        </div>
                        <p class="text-[#536e93] text-sm mb-6">Perfect for small shops just starting to organize data.</p>
                        <button class="w-full py-2.5 rounded-lg border border-primary text-primary font-bold text-sm mb-6 hover:bg-primary/5 transition-colors">Choose Starter</button>
                        <ul class="flex flex-col gap-3 text-sm text-[#3b4b5c]">
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Up to 1,000 customers</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Basic Segmentation</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Monthly Reports</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-gray-300 text-lg">close</span> SMS Integration</li>
                        </ul>
                    </div>
                    <!-- Growth Plan (Highlighted) -->
                    <div class="relative flex flex-col p-6 rounded-2xl border border-accent-gold bg-primary text-white h-full transform md:-translate-y-4 shadow-xl">
                        <div class="absolute top-0 right-0 left-0 -mt-3 flex justify-center">
                            <span class="bg-accent-gold text-primary text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Most Popular</span>
                        </div>
                        <h3 class="text-white text-xl font-bold mb-2 mt-2">Growth</h3>
                        <div class="mb-4">
                            <span class="text-4xl font-black text-accent-gold">GH₵ 350</span>
                            <span class="text-white/60 text-sm">/month</span>
                        </div>
                        <p class="text-white/70 text-sm mb-6">For growing businesses needing deeper insights.</p>
                        <button class="w-full py-2.5 rounded-lg bg-accent-gold text-primary font-bold text-sm mb-6 hover:bg-[#c29f2d] transition-colors">Choose Growth</button>
                        <ul class="flex flex-col gap-3 text-sm text-white/90">
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-accent-gold text-lg">check</span> Up to 10,000 customers</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-accent-gold text-lg">check</span> Advanced Segmentation</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-accent-gold text-lg">check</span> Sales Forecasting</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-accent-gold text-lg">check</span> SMS Integration (500 credits)</li>
                        </ul>
                    </div>
                    <!-- Pro Plan -->
                    <div class="flex flex-col p-6 rounded-2xl border border-[#d1dae5] bg-white h-full hover:border-primary/30 transition-colors">
                        <h3 class="text-primary text-xl font-bold mb-2">Pro</h3>
                        <div class="mb-4">
                            <span class="text-4xl font-black text-primary">GH₵ 700</span>
                            <span class="text-[#536e93] text-sm">/month</span>
                        </div>
                        <p class="text-[#536e93] text-sm mb-6">Full suite for established SMEs with multiple branches.</p>
                        <button class="w-full py-2.5 rounded-lg border border-primary text-primary font-bold text-sm mb-6 hover:bg-primary/5 transition-colors">Choose Pro</button>
                        <ul class="flex flex-col gap-3 text-sm text-[#3b4b5c]">
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Unlimited customers</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> All Growth Features</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Multi-user Access</li>
                            <li class="flex items-center gap-2"><span class="material-symbols-outlined text-green-500 text-lg">check</span> Priority Support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-primary text-white border-t border-white/10">
        <div class="layout-container flex justify-center py-12">
            <div class="layout-content-container flex flex-col max-w-[1200px] flex-1 px-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                    <div class="col-span-1 md:col-span-1">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-2xl">analytics</span>
                            <span class="font-bold text-lg">Customer 360</span>
                        </div>
                        <p class="text-white/60 text-sm leading-relaxed">Empowering Ghanaian businesses with data-driven insights.</p>
                    </div>
                    <div>
                        <h4 class="font-bold mb-4">Product</h4>
                        <ul class="flex flex-col gap-2 text-sm text-white/60">
                            <li><a class="hover:text-white" href="#features">Features</a></li>
                            <li><a class="hover:text-white" href="#pricing">Pricing</a></li>
                            <li><a class="hover:text-white" href="#">Case Studies</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-4">Company</h4>
                        <ul class="flex flex-col gap-2 text-sm text-white/60">
                            <li><a class="hover:text-white" href="#">About Us</a></li>
                            <li><a class="hover:text-white" href="#">Contact</a></li>
                            <li><a class="hover:text-white" href="#">Careers</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-4">Contact</h4>
                        <ul class="flex flex-col gap-2 text-sm text-white/60">
                            <li>Accra, Ghana</li>
                            <li><?php echo htmlspecialchars($contactEmail); ?></li>
                            <li><?php echo htmlspecialchars($contactPhone); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-white/40">
                    <p>&copy; <?php echo $currentYear; ?> Customer 360 Ghana. All rights reserved.</p>
                    <div class="flex gap-4">
                        <a class="hover:text-white" href="#">Privacy Policy</a>
                        <a class="hover:text-white" href="#">Terms of Service</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>
</body>
</html>
