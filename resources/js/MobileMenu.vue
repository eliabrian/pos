<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const profile = ref(null)
const categories = computed(() => {
  const catMap = new Map()

  products.value.forEach((p) => {
    if (p.category && !catMap.has(p.category)) {
      catMap.set(p.category, p.category_sort)
    }
  })

  return Array.from(catMap.entries())
    .map(([name, sort]) => ({ name, sort }))
    .sort((a, b) => a.sort - b.sort)
    .map((c) => c.name)
})
const products = ref([])
const loading = ref(false)
const selectedProduct = ref(null);
const selectedVariants = ref({});
const quantity = ref(1);
const note = ref('');
const cart = ref([]);
const isCartOpen = ref(false);
const selectedCategory = ref('Semua');
const isSubmitting = ref(false);
const isPaymentSuccess = ref(false);
let statusInterval = null;

onMounted(async () => {
    try {
        loading.value = true;
        const profileRes = await fetch('/api/mobile/profile');
        if (profileRes.ok) profile.value = await profileRes.json();

        await fetchProducts()

        if (!document.querySelector('script[src*="jokul-checkout"]')) {
            const script = document.createElement('script');
            const isLocal = window.location.hostname === 'localhost' || window.location.hostname.endsWith('.test');
            script.src = isLocal
                ? 'https://sandbox.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js'
                : 'https://jokul.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js';
            script.async = true;
            document.head.appendChild(script);
        }
    } catch (error) {
        console.error('Error fetching profile:', error);
    } finally {
        loading.value = false;
    }
})

onUnmounted(() => {
    if (statusInterval) clearInterval(statusInterval);
});

const fetchProducts = async () => {
    const response = await fetch('/api/mobile/products?include=category,variants');
    const rawProducts = await response.json();
    const includedData = rawProducts.included || []

    products.value = rawProducts.data.map((item) => {
        const categoryId = item.relationships?.category?.data?.id
        const categoryData = includedData.find(
            (inc) => inc.type === 'categories' && inc.id === categoryId,
        )

        const variantRefs = item.relationships?.variants?.data || []
        const parsedVariants = variantRefs
            .map((vRef) => {
                const variantData = includedData.find(
                    (inc) => inc.type === 'variants' && inc.id === vRef.id,
                )
                const rawItems = variantData?.attributes?.variant_items || []

                return {
                    id: variantData?.attributes?.id,
                    name: variantData?.attributes?.name,
                    is_required: variantData?.attributes?.is_required,
                    allow_multiple: variantData?.attributes?.allow_multiple,
                    items: rawItems.map((i) => ({ id: i.id, name: i.name, price: i.price || 0 })),
                }
            })
            .filter((v) => v.id)

        return {
            id: item.attributes.id,
            name: item.attributes.name,
            description: item.attributes.description,
            price: item.attributes.price,
            discount: item.attributes.discount,
            final_price: item.attributes.final_price,
            is_visible: item.attributes.is_visible,
            stock: item.attributes.stock,
            image: item.attributes.image,
            category_id: categoryData ? categoryData.id : null,
            category: categoryData ? categoryData.attributes.name : 'Lainnya',
            category_sort: categoryData ? categoryData.attributes.sort : 9999,
            variants: parsedVariants,
        }
    })
}

const cartTotalQty = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

const cartTotalPrice = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.total, 0);
});

const addToCart = () => {
    let readableVariants = [];
    for (const variantId in selectedVariants.value) {
        // ... (keep your existing readableVariants loop here) ...
        const selection = selectedVariants.value[variantId];
        if (!selection) continue;
        const chosenItemIds = Array.isArray(selection) ? selection : [selection];
        const variantDef = selectedProduct.value.variants.find(v => v.id == variantId);
        if (variantDef) {
            chosenItemIds.forEach(itemId => {
                const itemDef = variantDef.items.find(i => i.id == itemId);
                if (itemDef) readableVariants.push(itemDef.name);
            });
        }
    }

    // Calculate the price for just ONE of this item (base + variants)
    const unitPrice = modalTotal.value / quantity.value;

    const existingItemIndex = cart.value.findIndex(item => {
        return item.product.id === selectedProduct.value.id &&
               item.note === note.value &&
               JSON.stringify(item.variants) === JSON.stringify(selectedVariants.value);
    });

    if (existingItemIndex !== -1) {
        cart.value[existingItemIndex].quantity += quantity.value;
        cart.value[existingItemIndex].total = cart.value[existingItemIndex].quantity * cart.value[existingItemIndex].unit_price;
    } else {
        cart.value.push({
            cart_item_id: Date.now().toString(36) + Math.random().toString(36).substring(2),
            product: selectedProduct.value,
            quantity: quantity.value,
            unit_price: unitPrice, // <-- Save the unit price
            variants: JSON.parse(JSON.stringify(selectedVariants.value)),
            readable_variants: readableVariants.join(', '),
            note: note.value,
            total: modalTotal.value
        });
    }

    closeModal();
};

const increaseCartQty = (cartItemId) => {
    const item = cart.value.find(i => i.cart_item_id === cartItemId);
    if (item) {
        item.quantity++;
        item.total = item.quantity * item.unit_price; // Recalculate total
    }
};

const decreaseCartQty = (cartItemId) => {
    const index = cart.value.findIndex(i => i.cart_item_id === cartItemId);
    if (index !== -1) {
        if (cart.value[index].quantity > 1) {
            cart.value[index].quantity--;
            cart.value[index].total = cart.value[index].quantity * cart.value[index].unit_price; // Recalculate total
        } else {
            // Remove entirely if it hits 0
            cart.value.splice(index, 1);
            if (cart.value.length === 0) {
                isCartOpen.value = false; // Close cart if empty
            }
        }
    }
};

const removeFromCart = (cartItemId) => {
    cart.value = cart.value.filter(item => item.cart_item_id !== cartItemId);
    if (cart.value.length === 0) {
        isCartOpen.value = false;
    }
};

const checkOrderStatus = (receiptNumber) => {
    return new Promise((resolve, reject) => {
        if (statusInterval) clearInterval(statusInterval);

        statusInterval = setInterval(async () => {
            try {
                const response = await fetch(`/api/mobile/orders/${receiptNumber}/status`);
                const data = await response.json();

                if (data.status === 'completed') {
                    if (window.closeJokul) window.closeJokul();
                    clearInterval(statusInterval);
                    resolve(true);
                } else if (data.status === 'failed') {
                    clearInterval(statusInterval);
                    reject(new Error('Pembayaran gagal atau kadaluarsa.'));
                }
            } catch (error) {
                console.error('Error checking status', error);
            }
        }, 5000);
    });
};

const placeOrder = async () => {
    if (cart.value.length === 0) return;
    isSubmitting.value = true;

    // 1. Format the cart array to match EXACTLY what Laravel validates
    const payload = {
        payment_method: 'dynamic_qris', // Or map this if you give them a choice
        notes: '', // General order notes
        products: cart.value.map(item => {

            // Flatten our variant object into a single array of item IDs
            let flatVariantItemIds = [];
            for (const key in item.variants) {
                const selection = item.variants[key];
                if (selection) {
                    if (Array.isArray(selection)) {
                        flatVariantItemIds.push(...selection);
                    } else {
                        flatVariantItemIds.push(selection);
                    }
                }
            }

            return {
                id: item.product.id,
                quantity: item.quantity,
                variant_items: flatVariantItemIds,
                notes: item.note // The specific note for this item
            };
        })
    };

    try {
        // Because this is a POST request on a web route, Laravel needs the CSRF token.
        // The easiest way is to grab it from the meta tag in your blade file.
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const response = await fetch('/api/mobile/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok) {
            if (result.data.payment_url && payload.payment_method === 'dynamic_qris') {
                // FORCE REMOVE old DOKU wrapper if it lingered from a previous transaction
                const oldJokul = document.getElementById('jokul_checkout_modal');
                if (oldJokul) oldJokul.remove();

                if (window.loadJokulCheckout) {
                    window.loadJokulCheckout(result.data.payment_url);
                } else {
                    window.location.href = result.data.payment_url;
                }

                try {
                    await checkOrderStatus(result.data.receipt_number);

                    // IF SUCCESS:
                    cart.value = [];
                    isCartOpen.value = false;
                    isPaymentSuccess.value = true; // Trigger success screen

                } catch (err) {
                    alert(err.message);
                }
            } else {
                // Cash Payment / Pay at Cashier
                cart.value = [];
                isCartOpen.value = false;
                isPaymentSuccess.value = true;
            }
        } else {
            alert(result.message || 'Terjadi kesalahan saat membuat pesanan.');
        }

    } catch (error) {
        console.error('Error submitting order:', error);
        alert('Gagal terhubung ke server.');
    } finally {
        isSubmitting.value = false;
    }
};

const openModal = (product) => {
    if (product.stock === 0) return;
    selectedProduct.value = product;
    quantity.value = 1;
    selectedVariants.value = {};
    note.value = '';

    product.variants.forEach(variant => {
        if (variant.allow_multiple) {
            selectedVariants.value[variant.id] = (variant.is_required && variant.items.length > 0)
                ? [variant.items[0].id]
                : [];
        } else {
            selectedVariants.value[variant.id] = (variant.is_required && variant.items.length > 0)
                ? variant.items[0].id
                : null;
        }
    });
};

const closeModal = () => {
    selectedProduct.value = null;
}

const modalTotal = computed(() => {
    if (!selectedProduct.value) return 0;

    let basePrice = selectedProduct.value.final_price || selectedProduct.value.price;
    let variantExtra = 0;

    for (const variantId in selectedVariants.value) {
        const selection = selectedVariants.value[variantId];

        if (!selection) continue;

        const chosenItemIds = Array.isArray(selection) ? selection : [selection];

        const variantDef = selectedProduct.value.variants.find(v => v.id == variantId);

        if (variantDef) {
            chosenItemIds.forEach(itemId => {
                const itemDef = variantDef.items.find(i => i.id == itemId);
                if (itemDef) variantExtra += Number(itemDef.price);
            });
        }
    }

    return (basePrice + variantExtra) * quantity.value;
});

const todaysHours = computed(() => {
    if (!profile.value?.open_hours) return null;

    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const todayName = days[new Date().getDay()];

    return profile.value.open_hours.find(h => h.day === todayName);
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(price);
};
</script>

<template>
    <div class="max-w-md mx-auto bg-gray-50 min-h-screen shadow-lg pb-24">

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-48">
            <p class="text-gray-400 animate-pulse">Loading store details...</p>
        </div>

        <!-- Store Profile Header -->
        <div v-else-if="profile" class="bg-white pb-4 shadow-sm">

            <!-- Cover Image -->
            <div class="relative h-40 w-full bg-gray-200">
                <img
                    v-if="profile.cover_url"
                    :src="profile.cover_url"
                    alt="Cover"
                    class="w-full h-full object-cover"
                />
                <!-- Gradient overlay to make back buttons or tags readable if added later -->
                <div class="absolute inset-0 bg-linear-to-t from-black/40 to-transparent"></div>
            </div>

            <div class="px-5 relative">
                <!-- Logo overlapping the cover -->
                <div class="absolute -top-12 left-5">
                    <div class="h-24 w-24 rounded-full border-4 border-white bg-white shadow-md overflow-hidden flex items-center justify-center">
                        <img
                            v-if="profile.logo_url"
                            :src="profile.logo_url"
                            alt="Logo"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-gray-400 font-bold text-xl">{{ profile.name.charAt(0) }}</span>
                    </div>
                </div>

                <!-- Table Badge -->
                <div class="flex justify-end pt-3">
                    <span class="bg-gray-100 text-gray-800 text-xs px-3 py-1 rounded border border-gray-200 font-semibold">
                        {{ profile.table_name }}
                    </span>
                </div>

                <!-- Store Info -->
                <div class="mt-4">
                    <h1 class="text-xl font-bold text-gray-900">{{ profile.name }}</h1>
                    <p class="text-xs text-gray-400 mt-1 line-clamp-2">{{ profile.address }}</p>

                    <!-- Simplified Today's Hours -->
                    <div class="flex items-center gap-2 mt-3 text-xs text-gray-600 bg-gray-50 p-2 rounded-lg border border-gray-100">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium text-emerald-600">Buka</span>
                        <span v-if="todaysHours">• Tutup jam {{ todaysHours.close }}</span>
                        <span v-else class="text-gray-400">Hours not set</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="categories.length > 0" class="sticky top-0 z-30 bg-gray-50/95 backdrop-blur-sm py-3 px-4 border-b border-gray-200 overflow-x-auto whitespace-nowrap flex gap-2" style="scrollbar-width: none;">
            <button
                @click="selectedCategory = 'Semua'"
                :class="[
                    'px-4 py-1.5 rounded-full text-sm font-bold transition-all shadow-sm shrink-0',
                    selectedCategory === 'Semua' ? 'bg-blue-600 text-white border-transparent' : 'bg-white text-gray-600 border border-gray-200'
                ]"
            >
                Semua
            </button>

            <button
                v-for="cat in categories"
                :key="cat"
                @click="selectedCategory = cat"
                :class="[
                    'px-4 py-1.5 rounded-full text-sm font-bold transition-all shadow-sm shrink-0',
                    selectedCategory === cat ? 'bg-blue-600 text-white border-transparent' : 'bg-white text-gray-600 border border-gray-200'
                ]"
            >
                {{ cat }}
            </button>
        </div>

        <!-- Menu Section -->
        <div v-if="categories.length > 0" class="mt-4 px-4 pb-20">

            <!-- Loop through the computed category strings -->
            <div
                v-for="categoryName in categories"
                :key="categoryName"
                v-show="selectedCategory === 'Semua' || selectedCategory === categoryName"
                class="mb-8"
            >

                <!-- Category Title -->
                <h2 class="text-xl font-bold text-gray-500 mb-4">{{ categoryName }}</h2>

                <!-- Product Grid/List -->
                <div class="space-y-4">

                    <!-- Filter the flat products array to match the current category -->
                    <div
                        v-for="product in products.filter(p => p.category === categoryName)"
                        :key="product.id"
                        @click="openModal(product)"
                        :class="[
                            'bg-white rounded-xl p-3 shadow-sm border border-gray-100 flex gap-4 transition-all',
                            product.stock === 0 ? 'opacity-60 grayscale cursor-not-allowed' : 'active:scale-[0.98]'
                        ]"
                    >
                        <!-- Product Image -->
                        <div class="w-24 h-24 shrink-0 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center relative">
                            <img
                                v-if="product.image"
                                :src="`http://pos.test/` + product.image"
                                class="w-full h-full object-cover"
                            />
                            <svg v-else class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>

                            <!-- NEW: Habis Overlay on Image -->
                            <div v-if="product.stock === 0" class="absolute inset-0 bg-black/30 flex items-center justify-center backdrop-blur-[1px]">
                                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-sm tracking-wide uppercase">Habis</span>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900 leading-tight">{{ product.name }}</h3>
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ product.description }}</p>
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                <div class="flex flex-col">
                                    <!-- Discounted State -->
                                    <div v-if="product.final_price && product.final_price < product.price">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold">{{ formatPrice(product.final_price) }}</span>
                                            <span v-if="product.discount" class="bg-red-100 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded tracking-wide uppercase">
                                                {{ product.discount }}%
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-400 line-through mt-0.5 block">{{ formatPrice(product.price) }}</span>
                                    </div>

                                    <!-- Normal State -->
                                    <span v-else class="font-bold">
                                        {{ formatPrice(product.price) }}
                                    </span>
                                </div>

                                <!-- NEW: Show Plus Button OR Habis Text -->
                                <button v-if="product.stock > 0" class="bg-gray-100 text-gray-600 font-bold px-2 py-1.5 rounded text-sm hover:bg-gray-200 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                                <span v-else class="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded border border-red-100 shrink-0">
                                    Habis
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- FLOATING CART BUTTON -->
    <transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform translate-y-full opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-full opacity-0"
    >
        <div v-if="cart.length > 0" class="fixed bottom-6 left-0 right-0 px-4 z-40">
            <button
                @click="isCartOpen = true"
                class="w-full max-w-md mx-auto bg-blue-600 text-white rounded-2xl p-4 shadow-xl shadow-blue-200 flex items-center justify-between active:bg-blue-700 transition"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-blue-700 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ cartTotalQty }}
                    </div>
                    <span class="font-bold text-lg">Keranjang</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="font-bold text-lg">{{ formatPrice(cartTotalPrice) }}</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </button>
        </div>
    </transition>

    <!-- VARIANT BOTTOM SHEET MODAL -->
    <div v-if="selectedProduct" class="fixed inset-0 z-50 flex flex-col justify-end">
        <!-- Dark Backdrop (Click to close) -->
        <div class="absolute inset-0 bg-black/60 transition-opacity" @click="closeModal"></div>

        <!-- The Slide-Up Sheet -->
        <div class="relative bg-white w-full max-w-md mx-auto rounded-t-3xl flex flex-col max-h-[85vh] shadow-2xl animate-slide-up">

            <!-- Sticky Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-100 shrink-0">
                <h3 class="font-bold text-lg text-gray-900 truncate pr-4">{{ selectedProduct.name }}</h3>
                <button @click="closeModal" class="bg-gray-100 p-2 rounded-full text-gray-500 hover:bg-gray-200 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Scrollable Body: Variant Options -->
            <div class="p-4 overflow-y-auto flex-1 space-y-6">

                <p class="text-gray-500 text-sm mb-4">{{ selectedProduct.description }}</p>

                <div v-for="variant in selectedProduct.variants" :key="variant.id" class="mb-4">
                    <div class="flex justify-between items-end mb-3">
                        <h4 class="font-bold text-gray-900">{{ variant.name }}</h4>
                        <span v-if="variant.is_required" class="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded">Wajib</span>
                        <span v-else class="text-xs text-gray-500">Opsional</span>
                    </div>

                    <!-- Variant Items -->
                    <div class="space-y-3">
                        <label
                            v-for="item in variant.items"
                            :key="item.id"
                            class="flex items-center justify-between p-3 rounded-lg border border-gray-100 bg-gray-50/50"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Radio (Single Choice) or Checkbox (Multiple Choice) -->
                                <input
                                    :type="variant.allow_multiple ? 'checkbox' : 'radio'"
                                    :name="'variant_' + variant.id"
                                    :value="item.id"
                                    v-model="selectedVariants[variant.id]"
                                    class="w-5 h-5 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-gray-800">{{ item.name }}</span>
                            </div>
                            <span v-if="item.price > 0" class="text-gray-500 text-sm">+ {{ formatPrice(item.price) }}</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="block font-bold text-gray-900 mb-2">Catatan (Opsional)</label>
                    <textarea
                        v-model="note"
                        rows="2"
                        placeholder="Silahkan tambahkan catatan"
                        class="w-full p-3 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition text-sm text-gray-800 resize-none"
                    ></textarea>
                </div>
            </div>

            <!-- Sticky Footer: Quantity & Add to Cart -->
            <div class="p-4 border-t border-gray-100 bg-white shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-medium text-gray-700">Jumlah</span>
                    <div class="flex items-center gap-4 bg-gray-50 rounded-full p-1 border border-gray-200">
                        <button @click="quantity = Math.max(1, quantity - 1)" class="w-8 h-8 flex items-center justify-center rounded-full bg-white shadow-sm text-gray-600 font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="size-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                            </svg>
                        </button>
                        <span class="font-bold w-4 text-center">{{ quantity }}</span>
                        <button @click="quantity++" class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-600 shadow-sm text-white font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="size-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    @click="addToCart"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl text-center shadow-lg shadow-blue-200 active:bg-blue-700 transition"
                >
                    Tambah - {{ formatPrice(modalTotal) }}
                </button>
            </div>

        </div>
    </div>

    <!-- CART SUMMARY FULLSCREEN VIEW -->
    <transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div v-if="isCartOpen" class="fixed inset-0 z-50 bg-gray-50 flex flex-col max-w-md mx-auto shadow-2xl">

            <!-- Header -->
            <div class="bg-white px-4 py-4 shadow-sm flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="isCartOpen = false" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition">
                        <!-- Back Arrow Icon -->
                        <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <h2 class="text-xl font-bold text-gray-900">Keranjang</h2>
                </div>
                <!-- Shows the table number to reassure them -->
                <span v-if="profile" class="text-sm font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                    {{ profile.table_name }}
                </span>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-for="item in cart" :key="item.cart_item_id" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4">
                    <!-- NEW: Product Image Thumbnail -->
                    <div class="w-20 h-20 shrink-0 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center mt-1">
                        <img
                            v-if="item.product.image"
                            :src="`http://pos.test/` + item.product.image"
                            class="w-full h-full object-cover"
                        />
                        <!-- Placeholder if no image exists -->
                        <svg v-else class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <!-- Details & Controls -->
                    <div class="flex-1 flex flex-col justify-between">

                        <!-- Top: Text Details -->
                        <div>
                            <h3 class="font-bold text-gray-900 leading-tight">{{ item.product.name }}</h3>

                            <p v-if="item.readable_variants" class="text-xs text-gray-500 mt-1">
                                {{ item.readable_variants }}
                            </p>

                            <p v-if="item.note" class="text-xs text-gray-500 mt-1 bg-gray-50 p-1.5 rounded border border-gray-100 italic">
                                "{{ item.note }}"
                            </p>
                        </div>

                        <!-- Bottom: Price & Quantity Adjuster -->
                        <div class="flex justify-between items-center mt-3">
                            <span class="font-bold text-gray-900">{{ formatPrice(item.total) }}</span>

                            <!-- Quantity Adjuster -->
                            <div class="flex items-center gap-3 bg-gray-50 rounded-full p-1 border border-gray-200">
                                <button @click="decreaseCartQty(item.cart_item_id)" class="w-8 h-8 flex items-center justify-center rounded-full bg-white shadow-sm text-gray-600 font-bold active:bg-gray-100 transition">
                                    <svg v-if="item.quantity === 1" class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span v-else>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="size-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                        </svg>
                                    </span>
                                </button>

                                <span class="font-bold text-sm w-4 text-center">{{ item.quantity }}</span>

                                <button @click="increaseCartQty(item.cart_item_id)" class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-600 shadow-sm text-white font-bold active:bg-blue-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor" class="size-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Sticky Footer: Checkout -->
            <div class="bg-white p-5 border-t border-gray-100 shrink-0">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-gray-600 font-medium">Total Pembayaran</span>
                    <span class="font-extrabold text-xl text-gray-900">{{ formatPrice(cartTotalPrice) }}</span>
                </div>

                <button
                    @click="placeOrder"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl text-center shadow-lg shadow-blue-200 active:bg-blue-700 transition"
                >
                    Pesan Sekarang
                </button>
            </div>

        </div>
    </transition>

    <!-- SUCCESS FULLSCREEN VIEW -->
    <transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="isPaymentSuccess" class="fixed inset-0 z-100 bg-white flex flex-col items-center justify-center p-6 text-center">
            <!-- Success Animated Icon -->
            <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6 shadow-inner shadow-green-200">
                <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Pesanan Diterima!</h2>
            <p class="text-gray-500 mb-8 max-w-xs">
                Pembayaran telah berhasil dikonfirmasi. Dapur sedang menyiapkan pesanan Anda ke <span class="font-bold text-gray-900">{{ profile?.table_name }}</span>.
            </p>

            <button
                @click="isPaymentSuccess = false"
                class="bg-gray-100 text-gray-800 font-bold py-3.5 px-8 rounded-xl active:bg-gray-200 transition"
            >
                Pesan Menu Lainnya
            </button>
        </div>
    </transition>
</template>
