<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class AdacelaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];

        foreach ([
            'Freshwater Fish' => ['freshwater-fish', 'Freshwater aquarium fish'],
            'Fish Accessories' => ['fish-accessories', 'Aquarium equipment and supplies'],
            'Aquariums' => ['aquariums', 'Aquariums and tank setups'],
        ] as $name => [$slug, $description]) {
            $category = Category::withTrashed()->firstOrNew(['slug' => $slug]);
            $category->fill([
                'name' => $name,
                'description' => $description,
                'status' => true,
            ]);
            $category->deleted_at = null;
            $category->save();
            $categories[$name] = $category;
        }

        $products = [
            [
                'sku' => 'BETTA-HM-001',
                'name' => 'Halfmoon Betta',
                'category' => 'Freshwater Fish',
                'price' => 450,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ7J9tWlLqxYl6qUOnemIwelkMwkUWTy5IxAEYJUjvJFA&s=10',
                'description' => 'A vibrant, long-finned freshwater betta. Keep singly in a warm, gently filtered aquarium.',
            ],
            [
                'sku' => 'GUPPY-RT-002',
                'name' => 'Red Tuxedo Guppy',
                'category' => 'Freshwater Fish',
                'price' => 120,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT0wxXKxNUQJnSTJacuyjpsU1jqlOwlVwXY6fcGYpxSRA&s=10',
                'description' => 'A lively guppy with a vivid red tail and tuxedo-patterned body. Best kept in a peaceful group with stable, clean water.',
            ],
            [
                'sku' => 'TETRA-DN-003',
                'name' => 'Diamond Neon Tetra',
                'category' => 'Freshwater Fish',
                'price' => 150,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR-j-fM2wK91LZtcuhn1PWRz3sBwYwGtispYAHXt2SZ8g&s=10',
                'description' => 'A shimmering blue-and-red schooling tetra. Keep a group of at least six in a planted aquarium with compatible tankmates.',
            ],
            [
                'sku' => 'GOLDFISH-RANCHU-004',
                'name' => 'Ranchu Goldfish',
                'category' => 'Freshwater Fish',
                'price' => 850,
                'stock_quantity' => 0,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Goldfish_Ranchu_2.jpg?utm_source=en.wikipedia.org&utm_campaign=index&utm_content=original',
                'description' => 'A rounded, short-bodied fancy goldfish prized for its head growth and lack of a dorsal fin. Provide cool, clean water, ample swimming space, and strong filtration.',
            ],
            [
                'sku' => 'AROWANA-SR-005',
                'name' => 'Super Red Arowana',
                'category' => 'Freshwater Fish',
                'price' => 18500,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRJWnuOQL0I04GxRb6yQLoz6inF_PxK_sHqFh2DjfSjPg&s=10',
                'description' => 'A large, powerful arowana for experienced keepers with a very spacious, covered aquarium. Source only from lawful, documented captive-bred stock and check local regulations.',
            ],
            [
                'sku' => 'OSCAR-TIGER-006',
                'name' => 'Tiger Oscar',
                'category' => 'Freshwater Fish',
                'price' => 650,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQOm9dGUW_mOObaV4sOixoEScTsW4SdKcFqcKDNKzzsuw&s=10',
                'description' => 'A bold, intelligent cichlid with orange tiger-like markings. Grows large and needs a spacious aquarium, robust filtration, and carefully selected tankmates.',
            ],
            [
                'sku' => 'FILTER-SPONGE-007',
                'name' => 'Sponge Aquarium Filter',
                'category' => 'Fish Accessories',
                'price' => 399,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS0HFyrHTvVeKfyfv665j8PZaH9BDVSpPlkyypoWcCe5w&s=10',
                'description' => 'A gentle air-driven filter for biological filtration in small aquariums and fry tanks.',
            ],
            [
                'sku' => 'HEATER-ADJUSTABLE-008',
                'name' => 'Adjustable Aquarium Heater',
                'category' => 'Fish Accessories',
                'price' => 549,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTXJhs1TTT5iSUV4iyZXUszIlodZsHuXXaesiO1mV3XkQ&s=10',
                'description' => 'An adjustable submersible heater to help maintain a stable temperature for tropical fish.',
            ],
            [
                'sku' => 'LIGHT-FULL-SPECTRUM-009',
                'name' => 'Full-Spectrum LED Light',
                'category' => 'Fish Accessories',
                'price' => 699,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSTXCTFb1AiPZbxL6q2hBluf6iIelA74XfRbdQr2XbTug&s=10',
                'description' => 'A low-profile aquarium light with a balanced spectrum for viewing fish and illuminating aquatic plants.',
            ],
            [
                'sku' => 'FOOD-TROPICAL-FLAKES-010',
                'name' => 'Tropical Fish Flakes',
                'category' => 'Fish Accessories',
                'price' => 180,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSEmxdA6_qqIASgybdvFY-BsCS6xnL_KjYLTOV9M9cuBw&s=10',
                'description' => 'A daily staple flake food for many community tropical fish. Feed small portions they can finish promptly.',
            ],
            [
                'sku' => 'CONDITIONER-WATER-011',
                'name' => 'Water Conditioner',
                'category' => 'Fish Accessories',
                'price' => 250,
                'stock_quantity' => 0,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcShRjxzYTo_DnU2MedP8re1kJTRURZjvV2jCn3cKqwPOg&s=10',
                'description' => 'A tap-water treatment for aquarium setup and water changes. Follow the product label for dosage.',
            ],
            [
                'sku' => 'AQUARIUM-NANO-012',
                'name' => '10-Liter Nano Aquarium',
                'category' => 'Aquariums',
                'price' => 999,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcResmLYvTlpNfZnOXu2wbeSDG3q4qonUMtYKm5-2997F23yIWq5hmUUgRQ&s=10',
                'description' => 'A compact glass aquarium for a carefully planned nano setup. Not suitable for every fish species.',
            ],
            [
                'sku' => 'AQUARIUM-STARTER-013',
                'name' => '40-Liter Starter Aquarium',
                'category' => 'Aquariums',
                'price' => 2499,
                'stock_quantity' => 20,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRf9Ne1I1uw5QEVLD_HIJ1AI8FIEYv9dq9M4nl_w8RVRmJ3QIlTednjEak&s=10',
                'description' => 'A versatile glass tank for a small freshwater community, with room for filtration and aquascaping.',
            ],
            [
                'sku' => 'AQUARIUM-COMMUNITY-014',
                'name' => '80-Liter Community Aquarium',
                'category' => 'Aquariums',
                'price' => 4999,
                'stock_quantity' => 20,
                'image' => 'https://aquacadabra.com/cdn/shop/files/22xm2saxdzk.jpg?v=1762950380&width=1946',
                'description' => 'A spacious glass aquarium for a thoughtfully stocked freshwater community. Stand and equipment sold separately.',
            ],
        ];

        foreach ($products as $data) {
            $product = Product::withTrashed()->firstOrNew(['sku' => $data['sku']]);
            $product->fill([
                'name' => $data['name'],
                'category_id' => $categories[$data['category']]->id,
                'description' => $data['description'],
                'price' => $data['price'],
                'sale_price' => null,
                'stock_quantity' => $data['stock_quantity'],
                'brand' => 'Adacela',
                'status' => true,
                'featured' => false,
            ]);
            $product->deleted_at = null;
            $product->save();

            $product->images()->updateOrCreate(
                ['is_primary' => true],
                ['image' => $data['image'], 'sort_order' => 0]
            );
        }
    }
}
