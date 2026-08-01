<?php

namespace Database\Seeders;

use App\Models\PortfolioItem;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Database\Factories\VendorFactory;
use Illuminate\Database\Seeder;

/**
 * Local demo dataset for Smart Search. See smart-search.md at the repo root.
 *
 * LOCAL ONLY — never run this against Railway. The Flutter team tests against
 * that database and these fake vendors would show up in their app.
 *
 *   php artisan db:seed --class=HaflatiDemoSeeder   add the demo data
 *   php artisan migrate:fresh --seed                wipe and rebuild everything
 *
 * Running db:seed twice inserts twice (80 vendors, not 40). Use
 * migrate:fresh --seed when you want a clean set.
 *
 * Three parts:
 *   1. ~40 visible vendors across all 10 vendor_type values, each with products
 *      and (for service vendors) portfolio items.
 *   2. BAIT rows that must NEVER appear in a search result.
 *   3. A printed summary, including how to find the bait ids again.
 */
class HaflatiDemoSeeder extends Seeder
{
    /**
     * How many vendors per type. Slightly more of the types customers search
     * most, so ranking has something to actually rank.
     */
    private const PER_TYPE = [
        'photographer' => 6,
        'weddingHall'  => 5,
        'makeupArtist' => 4,
        'dj'           => 4,
        'flowers'      => 4,
        'cakes'        => 4,
        'dresses'      => 4,
        'gifts'        => 3,
        'accessories'  => 3,
        'candles'      => 3,
    ];

    /** Portfolio items per service vendor — index-only, never a search result. */
    private const PORTFOLIO_TITLES = [
        'photographer' => [
            ['تصوير عرس في صالة الياسمين', 'تغطية كاملة لحفل زفاف بحضور ثلاثمئة ضيف، تصوير فوتوغرافي وفيديو.'],
            ['جلسة تصوير خطوبة خارجية', 'جلسة تصوير في حديقة تشرين وقت الغروب مع تعديل احترافي للصور.'],
            ['تغطية حفل زفاف كامل', 'تصوير يوم العرس من تحضير العروس حتى نهاية السهرة.'],
            ['تصوير عيد ميلاد أطفال', 'تغطية حفلة عيد ميلاد مع طباعة فورية للصور للضيوف.'],
            ['ألبوم عرس مطبوع', 'ألبوم زفاف فاخر بتصميم خاص وطباعة عالية الجودة.'],
        ],
        'makeupArtist' => [
            ['مكياج عروس ناعم', 'مكياج عروس بألوان هادئة مع تسريحة شعر مرفوعة.'],
            ['مكياج سهرة قوي', 'مكياج سهرة بألوان دافئة ورموش كثيفة لحفلة خطوبة.'],
            ['تسريحة شعر عروس', 'تسريحة شعر مرفوعة مع إكسسوار وتاج للعروس.'],
            ['عناية بالبشرة قبل العرس', 'برنامج عناية بالبشرة على مدى أسبوعين قبل يوم العرس.'],
        ],
        'dj' => [
            ['إحياء حفل زفاف', 'تنسيق موسيقي كامل لحفل زفاف مع زفة عريس وإضاءة ليزر.'],
            ['سهرة خطوبة', 'تغطية موسيقية لحفلة خطوبة بأغاني عربية وأجنبية.'],
            ['حفل تخرج جامعي', 'نظام صوت وإضاءة لحفل تخرج مع مقدم حفل.'],
            ['زفة عريس تقليدية', 'زفة تقليدية بالطبل والمزمار مع تنسيق دخول العروسين.'],
        ],
        'weddingHall' => [
            ['حفل زفاف لأربعمئة ضيف', 'استضافة حفل زفاف كامل مع ضيافة وتنسيق زهور وإضاءة.'],
            ['حفلة خطوبة عائلية', 'استضافة حفلة خطوبة بحضور مئة وخمسين شخص مع بوفيه.'],
            ['تنسيق قاعة بالزهور', 'تنسيق كامل للقاعة بالورد الطبيعي ومنصة العروسين.'],
            ['حفل تخرج', 'استضافة حفل تخرج مع مسرح وإضاءة ونظام صوت.'],
        ],
    ];

    public function run(): void
    {
        $this->command->info('Seeding Haflati demo data (LOCAL ONLY)...');

        $visible = $this->seedVisibleVendors();
        $bait    = $this->seedBaitRows();

        $this->summary($visible, $bait);
    }

    /** ~40 approved + active vendors with products and portfolio items. */
    private function seedVisibleVendors(): array
    {
        $vendorCount = 0;
        $productCount = 0;
        $portfolioCount = 0;

        foreach (self::PER_TYPE as $type => $count) {
            for ($i = 0; $i < $count; $i++) {
                $vendor = Vendor::factory()->type($type)->create();
                $vendorCount++;

                // 2-4 listings each. The parent's booking_style decides whether
                // these are products (order) or services (appointment).
                $productCount += $this->seedProducts($vendor, fake()->numberBetween(2, 4));

                // Portfolio only makes sense for the four service types.
                // Index-only: it makes the VENDOR findable, it is never returned
                // as a search result itself.
                if (isset(self::PORTFOLIO_TITLES[$type])) {
                    $portfolioCount += $this->seedPortfolio($vendor, fake()->numberBetween(2, 4));
                }
            }
        }

        // One deliberately thin service vendor: empty bio, generic package names,
        // but a rich portfolio. He is ONLY findable through his portfolio text —
        // this is the row that proves portfolio indexing is worth doing.
        $thin = Vendor::factory()->type('photographer')->create([
            'business_name' => 'استوديو الفن الرقمي',
            'bio'           => null,
        ]);
        $vendorCount++;

        foreach (['باقة 1', 'باقة 2', 'باقة 3'] as $name) {
            VendorProduct::factory()->forType('photographer', 'appointment')->create([
                'vendor_id'   => $thin->id,
                'name'        => $name,
                'description' => null,
            ]);
            $productCount++;
        }

        foreach (self::PORTFOLIO_TITLES['photographer'] as [$title, $description]) {
            PortfolioItem::create([
                'vendor_id'   => $thin->id,
                'title'       => $title,
                'description' => $description,
            ]);
            $portfolioCount++;
        }

        return [
            'vendors'   => $vendorCount,
            'products'  => $productCount,
            'portfolio' => $portfolioCount,
        ];
    }

    /**
     * Rows that must NEVER surface in a search result. The leak audit searches
     * for these specifically. Prefixed BAIT- so they are findable again after a
     * migrate:fresh, when every id changes.
     */
    private function seedBaitRows(): array
    {
        // 3 vendors that were never KYC-approved.
        $unapproved = Vendor::factory()->count(3)->unapproved()->create();
        foreach ($unapproved as $vendor) {
            $this->seedProducts($vendor, 2);
        }

        // 2 banned vendors — with products that look perfectly fine on their own.
        // A product result inherits its vendor's flags, so these must vanish too.
        $banned = Vendor::factory()->count(2)->banned()->create();
        foreach ($banned as $vendor) {
            $this->seedProducts($vendor, 2);
        }

        // A healthy, visible vendor who happens to have hidden items.
        // This is the sharpest test: the vendor SHOULD appear, these rows must not.
        $host = Vendor::factory()->type('flowers')->create([
            'business_name' => 'زهور الربيع',
        ]);

        $unavailable = VendorProduct::factory()
            ->count(5)
            ->forType('flowers', 'order')
            ->unavailable()
            ->create(['vendor_id' => $host->id]);

        $outOfStock = VendorProduct::factory()
            ->count(2)
            ->forType('flowers', 'order')
            ->outOfStock()
            ->create(['vendor_id' => $host->id]);

        return [
            'unapproved_vendors' => $unapproved->pluck('id')->all(),
            'banned_vendors'     => $banned->pluck('id')->all(),
            'unavailable'        => $unavailable->pluck('id')->all(),
            'out_of_stock'       => $outOfStock->pluck('id')->all(),
            'bait_host_vendor'   => $host->id,
        ];
    }

    /** Listings that match the parent vendor's type and booking_style. */
    private function seedProducts(Vendor $vendor, int $count): int
    {
        VendorProduct::factory()
            ->count($count)
            ->forType($vendor->vendor_type, $vendor->booking_style)
            ->create(['vendor_id' => $vendor->id]);

        return $count;
    }

    private function seedPortfolio(Vendor $vendor, int $count): int
    {
        $items = fake()->randomElements(
            self::PORTFOLIO_TITLES[$vendor->vendor_type],
            $count
        );

        foreach ($items as [$title, $description]) {
            PortfolioItem::create([
                'vendor_id'   => $vendor->id,
                'title'       => $title,
                'description' => $description,
            ]);
        }

        return $count;
    }

    private function summary(array $visible, array $bait): void
    {
        $c = $this->command;

        $c->newLine();
        $c->info('=== Visible (these SHOULD appear in search) ===');
        $c->line("  vendors:         {$visible['vendors']}");
        $c->line("  products:        {$visible['products']}");
        $c->line("  portfolio items: {$visible['portfolio']}");

        $c->newLine();
        $c->warn('=== BAIT (these must NEVER appear in search) ===');
        $c->line('  unapproved vendors: ' . implode(', ', $bait['unapproved_vendors']));
        $c->line('  banned vendors:     ' . implode(', ', $bait['banned_vendors']));
        $c->line('  unavailable items:  ' . implode(', ', $bait['unavailable']));
        $c->line('  out-of-stock items: ' . implode(', ', $bait['out_of_stock']));
        $c->line("  (the last two belong to vendor {$bait['bait_host_vendor']}, "
            . 'who IS visible on purpose)');

        $c->newLine();
        $c->line('Ids change on every reseed. Find them again with:');
        $c->line("  SELECT id, business_name FROM vendors WHERE business_name LIKE 'BAIT-%';");
        $c->line("  SELECT id, name FROM vendor_products WHERE name LIKE 'BAIT-%';");
        $c->newLine();
    }
}
