<template>
  <div class="main-content">
    <breadcumb :page="$t('ProductDetails')" :folder="$t('Products')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div
      v-if="!isLoading"
      class="pd-root"
      :style="rootStyle"
    >
      <!-- Top Actions Row -->
      <div
        class="pd-actions"
        :style="{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: '20px',
          gap: '10px',
          flexWrap: 'wrap'
        }"
      >
        <button
          @click="goBack"
          :style="backBtnStyle"
          @mouseover="onBackBtnHover($event, true)"
          @mouseleave="onBackBtnHover($event, false)"
        >
          <lucide-icon name="arrow-left" :style="{ marginRight: '6px' }" />
          {{ $t('back') || 'Back' }}
        </button>

        <div :style="{ display: 'flex', gap: '10px' }">
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('products_edit')"
            :to="{ name: 'Edit_product', params: { id: product.id } }"
            class="btn btn-outline-primary d-inline-flex align-items-center"
            :style="{ borderRadius: '10px', fontWeight: '600', padding: '10px 18px' }"
          >
            <lucide-icon name="pencil" :style="{ marginRight: '6px', width: '16px', height: '16px' }" />
            {{ $t('EditProduct') || 'Edit Product' }}
          </router-link>
          <button
            @click="print_product()"
            :style="printBtnStyle"
            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(79,70,229,0.35)'"
            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(79,70,229,0.25)'"
          >
            <lucide-icon name="receipt" :style="{ marginRight: '6px' }" />
            {{ $t('print') }}
          </button>
        </div>
      </div>

      <div id="print_product">
        <!-- Hero Section -->
        <div
          class="pd-hero"
          :style="{
            background: 'linear-gradient(135deg, var(--primary-color-darker, #2e1065) 0%, var(--primary-color, #4f46e5) 100%)',
            borderRadius: '20px',
            padding: '32px',
            color: '#fff',
            marginBottom: '28px',
            boxShadow: '0 20px 40px rgba(109,40,217,0.15)',
            position: 'relative',
            overflow: 'hidden'
          }"
        >
          <!-- Absolute decorative shapes -->
          <div :style="{ position: 'absolute', right: '-40px', top: '-40px', width: '240px', height: '240px', borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }"></div>
          <div :style="{ position: 'absolute', left: '10%', bottom: '-60px', width: '180px', height: '180px', borderRadius: '50%', background: 'rgba(255,255,255,0.03)' }"></div>

          <div class="pd-hero-row" :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '30px', position: 'relative', zIndex: 1 }">
            <!-- Left: Image Box & Gallery Trigger -->
            <div :style="{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }">
              <div
                class="pd-hero-img"
                :style="{
                  width: '160px',
                  height: '160px',
                  borderRadius: '50%',
                  background: '#fff',
                  border: '4px solid rgba(255,255,255,0.2)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  overflow: 'hidden',
                  boxShadow: '0 8px 24px rgba(0,0,0,0.2)'
                }"
              >
                <img
                  :src="'/images/products/' + (productImages[0] || product.image || 'no-image.png')"
                  :alt="product.name"
                  :style="{ width: '100%', height: '100%', objectFit: 'contain' }"
                  @error="onImgError"
                />
              </div>
              <b-button
                v-if="productImages.length"
                size="sm"
                variant="light"
                @click="galleryModalOpen = true"
                class="d-inline-flex align-items-center"
                :style="{ borderRadius: '20px', fontWeight: '600', padding: '4px 14px', fontSize: '11px', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }"
              >
                <lucide-icon name="image" :style="{ marginRight: '4px', width: '12px', height: '12px' }" />
                View Gallery ({{ productImages.length }})
              </b-button>
            </div>

            <!-- Middle: Product Info details -->
            <div :style="{ flex: '1', minWidth: '280px' }">
              <h1 class="pd-hero-title" :style="{ margin: '0 0 12px 0', fontSize: '32px', fontWeight: '800', lineHeight: '1.2', letterSpacing: '-0.5px' }">
                {{ product.name }}
              </h1>

              <div :style="{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '18px' }">
                <span :style="heroBadge('rgba(255,255,255,0.15)')">
                  <lucide-icon name="tag" :style="{ marginRight: '4px', width: '13px', height: '13px' }" />
                  {{ product.type_name }}
                </span>
                <span :style="heroBadge('rgba(16,185,129,0.2)')" v-if="product.code">
                  <lucide-icon name="barcode" :style="{ marginRight: '4px', width: '13px', height: '13px' }" />
                  SKU: {{ product.code }}
                </span>
                <span :style="heroBadge('rgba(245,158,11,0.2)')" v-if="product.brand && product.brand !== 'N/D'">
                  <lucide-icon name="award" :style="{ marginRight: '4px', width: '13px', height: '13px' }" />
                  Brand: {{ product.brand }}
                </span>
                <span :style="heroBadge('rgba(6,182,212,0.2)')" v-if="product.type != 'is_service'">
                  <lucide-icon name="shield" :style="{ marginRight: '4px', width: '13px', height: '13px' }" />
                  Condition: New
                </span>
              </div>

              <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '20px', fontSize: '13px', opacity: '0.85' }">
                <span v-if="categoriesLine" :style="{ display: 'inline-flex', alignItems: 'center' }">
                  <lucide-icon name="folder" :style="{ marginRight: '6px', width: '14px', height: '14px' }" />
                  Category: <strong>{{ categoriesLine }}</strong>
                </span>
                <span v-if="subcategoriesLine" :style="{ display: 'inline-flex', alignItems: 'center' }">
                  <lucide-icon name="rows-2" :style="{ marginRight: '6px', width: '14px', height: '14px' }" />
                  Subcategory: <strong>{{ subcategoriesLine }}</strong>
                </span>
              </div>
            </div>

            <!-- Right: Premium Price card and Quick Action Panel -->
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '12px', minWidth: '220px' }">
              <div
                class="pd-hero-price"
                :style="{
                  background: 'rgba(255,255,255,0.1)',
                  border: '1px solid rgba(255,255,255,0.15)',
                  padding: '20px 24px',
                  borderRadius: '16px',
                  textAlign: 'center',
                  backdropFilter: 'blur(12px)',
                  boxShadow: '0 8px 32px rgba(0,0,0,0.1)'
                }"
              >
                <div :style="{ fontSize: '11px', opacity: '0.7', textTransform: 'uppercase', letterSpacing: '1.5px', fontWeight: '700' }">
                  {{ $t('Price') || 'Selling Price' }}
                </div>
                <div :style="{ fontSize: '32px', fontWeight: '800', marginTop: '6px', letterSpacing: '-0.5px' }">
                  {{ formatPriceWithSymbol(currentUser && currentUser.currency, product.price, 2) }}
                </div>
              </div>

              <!-- Barcode Action button -->
              <b-button
                v-if="product.type != 'is_variant' && product.code"
                variant="outline-light"
                @click="barcodeModalOpen = true"
                class="d-inline-flex align-items-center justify-content-center"
                :style="{ borderRadius: '12px', fontWeight: '600', padding: '10px 16px', fontSize: '13px' }"
              >
                <lucide-icon name="barcode" :style="{ marginRight: '6px', width: '15px', height: '15px' }" />
                {{ $t('GenerateBarcode') || 'Generate Barcode' }}
              </b-button>
            </div>
          </div>
        </div>

        <!-- Summary Cards Row -->
        <h5 class="mb-3 font-weight-bold" :style="{ color: pdTheme.cardHeaderText }">Summary Card</h5>
        <div
          :style="{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
            gap: '20px',
            marginBottom: '28px'
          }"
        >
          <!-- Pricing Summary -->
          <div :style="summaryCardStyle">
            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }">
              <div>
                <h6 :style="summaryCardHeaderTitleStyle">Pricing Summary</h6>
              </div>
              <div :style="summaryCardIconWrapperStyle('var(--primary-color, #4f46e5)', 'var(--primary-color-soft, #eef2ff)')">
                <lucide-icon name="trending-up" style="width: 18px; height: 18px;" />
              </div>
            </div>
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '10px' }">
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Base Cost</span>
                <span :style="summaryCardValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.cost, 2) }}</span>
              </div>
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Selling Price</span>
                <span :style="{ ...summaryCardValueStyle, color: '#10b981' }">
                  {{ formatPriceWithSymbol(currentUser && currentUser.currency, product.price, 2) }}
                  <lucide-icon name="arrow-up" style="width: 13px; height: 13px; vertical-align: middle; margin-left: 2px;" />
                </span>
              </div>
            </div>
          </div>

          <!-- Margin & Profit -->
          <div :style="summaryCardStyle">
            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }">
              <div>
                <h6 :style="summaryCardHeaderTitleStyle">Margin & Profit</h6>
              </div>
              <div :style="summaryCardIconWrapperStyle('#0ea5e9', '#f0f9ff')">
                <lucide-icon name="coins" style="width: 18px; height: 18px;" />
              </div>
            </div>
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '10px' }">
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Wholesale Price</span>
                <span :style="summaryCardValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.wholesale_price, 2) }}</span>
              </div>
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Profit Margin</span>
                <span :style="{ ...summaryCardValueStyle, color: '#10b981' }">{{ profitMargin }}%</span>
              </div>
            </div>
          </div>

          <!-- Stock Status -->
          <div :style="summaryCardStyle">
            <div :style="{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }">
              <div>
                <h6 :style="summaryCardHeaderTitleStyle">Stock Status</h6>
              </div>
              <div :style="summaryCardIconWrapperStyle('#10b981', '#ecfdf5')">
                <lucide-icon name="package" style="width: 18px; height: 18px;" />
              </div>
            </div>
            <div :style="{ display: 'flex', flexDirection: 'column', gap: '10px' }">
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Stock Alert limit</span>
                <span :style="{ ...summaryCardValueStyle, color: '#ef4444' }">{{ formatNumber(product.stock_alert, 2) }}</span>
              </div>
              <div :style="summaryCardRowStyle">
                <span :style="summaryCardLabelStyle">Available Serial Numbers</span>
                <span :style="{ ...summaryCardValueStyle, color: '#10b981' }">{{ serialTotalRows }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Spec List and Notes Row -->
        <div
          :style="{
            display: 'grid',
            gridTemplateColumns: 'minmax(0, 1.8fr) minmax(0, 1.2fr)',
            gap: '24px',
            marginBottom: '28px'
          }"
          class="pd-main-grid"
        >
          <!-- Left: Key Specs -->
          <div :style="cardStyle">
            <div :style="cardHeaderStyle">
              <lucide-icon name="info" :style="{ marginRight: '8px', color: 'var(--primary-color, #4f46e5)' }" />
              Key Physical Specs
            </div>
            <div :style="{ padding: '16px 20px' }">
              <table class="w-100 table-specs" :style="{ color: pdTheme.valueColor }">
                <tbody>
                  <tr :style="specRowStyle">
                    <td :style="specLabelStyle">Warranty Period</td>
                    <td :style="specValueStyle" class="text-right">
                      {{ product.warranty_period ? `${product.warranty_period} ${$t(product.warranty_unit)}` : '—' }}
                    </td>
                  </tr>
                  <tr :style="specRowStyle">
                    <td :style="specLabelStyle">Guarantee Period</td>
                    <td :style="specValueStyle" class="text-right">
                      {{ product.has_guarantee ? `${product.guarantee_period} ${$t(product.guarantee_unit)}` : '—' }}
                    </td>
                  </tr>
                  <tr :style="specRowStyle">
                    <td :style="specLabelStyle">Weight</td>
                    <td :style="specValueStyle" class="text-right">
                      {{ product.weight ? `${formatNumber(product.weight, 2)} kg` : '—' }}
                    </td>
                  </tr>
                  <tr :style="specRowStyle">
                    <td :style="specLabelStyle">UoM (Unit of Measure)</td>
                    <td :style="specValueStyle" class="text-right">
                      {{ product.unit || '—' }}
                    </td>
                  </tr>
                  <tr :style="specRowStyle">
                    <td :style="specLabelStyle">Tax rate</td>
                    <td :style="specValueStyle" class="text-right">
                      {{ formatNumber(product.taxe, 2) }}% ({{ product.tax_method }})
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: Product Notes -->
          <div :style="cardStyle">
            <div :style="cardHeaderStyle">
              <lucide-icon name="file-pen" :style="{ marginRight: '8px', color: '#8b5cf6' }" />
              Product Notes
            </div>
            <div :style="{ padding: '20px', minHeight: '150px' }">
              <div v-if="product.note" :style="{ color: pdTheme.noteText, lineHeight: '1.6', fontSize: '13px' }">
                {{ product.note }}
              </div>
              <div v-else class="text-center text-muted italic p-4" :style="{ fontSize: '13px' }">
                No custom notes available for this product.
              </div>
            </div>
          </div>
        </div>

        <!-- Extended Details Section (Tabs) -->
        <div :style="cardStyle" class="mb-4">
          <div :style="cardHeaderStyle">
            <lucide-icon name="list" :style="{ marginRight: '8px', color: '#10b981' }" />
            Extended Details
          </div>

          <div class="px-3 pt-3">
            <b-tabs content-class="mt-3" nav-class="custom-nav-tabs">
              <!-- Warehouse Stock Tab -->
              <b-tab title="Warehouse Stock" active>
                <div
                  v-if="product.CountQTY && product.CountQTY.length"
                  :style="{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                    gap: '14px',
                    padding: '10px 0 20px 0'
                  }"
                >
                  <div
                    v-for="(w, i) in product.CountQTY"
                    :key="i"
                    :style="{
                      background: pdTheme.warehouseCardBg,
                      border: `1px solid ${pdTheme.warehouseCardBorder}`,
                      borderRadius: '12px',
                      padding: '16px',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px'
                    }"
                  >
                    <div
                      :style="{
                        width: '40px', height: '40px',
                        borderRadius: '10px',
                        background: '#0ea5e9',
                        color: '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '18px',
                        flexShrink: 0
                      }"
                    ><lucide-icon name="store" /></div>
                    <div :style="{ flex: 1 }">
                      <div :style="{ fontSize: '11px', color: pdTheme.warehouseLabel, fontWeight: '600', textTransform: 'uppercase' }">{{ w.mag }}</div>
                      <div :style="{ fontSize: '18px', fontWeight: '700', color: pdTheme.warehouseValue }">
                        {{ formatNumber(w.qte || 0, 2) }}
                        <span :style="{ fontSize: '12px', color: pdTheme.warehouseUnit, fontWeight: '500' }">{{ product.unit }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center text-muted p-4 italic">No warehouse stock data available.</div>
              </b-tab>

              <!-- Warranty & Terms Tab -->
              <b-tab title="Warranty & Terms">
                <div :style="{ padding: '10px 10px 20px 10px', color: pdTheme.valueColor }">
                  <h6 class="font-weight-bold mb-2">Warranty Conditions</h6>
                  <p v-if="product.warranty_terms" :style="{ fontSize: '13px', lineHeight: '1.6' }">
                    {{ product.warranty_terms }}
                  </p>
                  <div v-else class="text-muted italic" :style="{ fontSize: '13px' }">
                    Terms & Conditions: Standard warranty applies. Subject to manufacturer's policy validation.
                  </div>
                </div>
              </b-tab>

              <!-- Sales History Tab -->
              <b-tab title="Sales History">
                <div :style="{ padding: '10px 0 20px 0' }">
                  <div :style="{ display: 'flex', gap: '10px', flexWrap: 'wrap', marginBottom: '14px' }">
                    <input
                      v-model="salesSearch"
                      @input="debouncedLoadSalesHistory"
                      :placeholder="$t('Search_by_Ref_Customer_or_Warehouse') || 'Search by Ref, Customer or Warehouse'"
                      :style="{ flex: '1 1 240px', minHeight: '36px', border: '1px solid ' + pdTheme.cardBorder, borderRadius: '6px', padding: '8px 10px', background: pdTheme.cardBg, color: pdTheme.valueColor, fontSize: '13px' }"
                    />
                  </div>

                  <div v-if="salesLoading" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor }">
                    <div class="spinner spinner-primary" :style="{ display: 'inline-block', marginRight: '10px' }"></div>
                    {{ $t('Loading') || 'Loading...' }}
                  </div>

                  <div v-else-if="!salesHistory.length" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor, fontStyle: 'italic' }">
                    <lucide-icon name="info" :style="{ marginRight: '6px' }" />
                    No sales history found for this product.
                  </div>

                  <div v-else>
                    <div :style="{ overflowX: 'auto' }">
                      <table :style="tableStyle">
                        <thead>
                          <tr>
                            <th :style="thStyle">{{ $t('date') || 'Date' }}</th>
                            <th :style="thStyle">{{ $t('Reference') || 'Reference' }}</th>
                            <th :style="thStyle">{{ $t('Customer') || 'Customer' }}</th>
                            <th :style="thStyle">{{ $t('Warehouse') || 'Warehouse' }}</th>
                            <th :style="thStyle">{{ $t('Quantity') || 'Quantity' }}</th>
                            <th :style="thStyle">{{ $t('price') || 'Price' }}</th>
                            <th :style="thStyle">{{ $t('Total') || 'Total' }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="s in salesHistory" :key="s.id" :style="trHover">
                            <td :style="tdStyle">{{ formatDisplayDate(s.date) }}</td>
                            <td :style="tdStyle">
                              <router-link :to="'/app/sales/detail/' + s.sale_id" :style="{ color: 'var(--primary-color, #4f46e5)', fontWeight: '700' }">
                                {{ s.ref }}
                              </router-link>
                            </td>
                            <td :style="tdStyle">{{ s.client_name }}</td>
                            <td :style="tdStyle">{{ s.warehouse_name }}</td>
                            <td :style="tdStyle">{{ formatNumber(s.quantity, 2) }} {{ s.unit }}</td>
                            <td :style="tdStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, s.price, 2) }}</td>
                            <td :style="tdStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, s.total, 2) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="salesHistory.length && salesTotalRows > salesPerPage" class="d-flex justify-content-between align-items-center mt-3">
                      <div :style="{ color: pdTheme.keyColor, fontSize: '13px' }">
                        Showing {{ (salesCurrentPage - 1) * salesPerPage + 1 }} to {{ Math.min(salesCurrentPage * salesPerPage, salesTotalRows) }} of {{ salesTotalRows }}
                      </div>
                      <b-pagination
                        v-model="salesCurrentPage"
                        :total-rows="salesTotalRows"
                        :per-page="salesPerPage"
                        @change="handleSalesPageChange"
                        align="right"
                        size="sm"
                        class="my-0"
                      ></b-pagination>
                    </div>
                  </div>
                </div>
              </b-tab>

              <!-- Purchase History Tab -->
              <b-tab title="Purchase History">
                <div :style="{ padding: '10px 0 20px 0' }">
                  <div :style="{ display: 'flex', gap: '10px', flexWrap: 'wrap', marginBottom: '14px' }">
                    <input
                      v-model="purchasesSearch"
                      @input="debouncedLoadPurchasesHistory"
                      :placeholder="$t('Search_by_Ref_Supplier_or_Warehouse') || 'Search by Ref, Supplier or Warehouse'"
                      :style="{ flex: '1 1 240px', minHeight: '36px', border: '1px solid ' + pdTheme.cardBorder, borderRadius: '6px', padding: '8px 10px', background: pdTheme.cardBg, color: pdTheme.valueColor, fontSize: '13px' }"
                    />
                  </div>

                  <div v-if="purchasesLoading" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor }">
                    <div class="spinner spinner-primary" :style="{ display: 'inline-block', marginRight: '10px' }"></div>
                    {{ $t('Loading') || 'Loading...' }}
                  </div>

                  <div v-else-if="!purchasesHistory.length" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor, fontStyle: 'italic' }">
                    <lucide-icon name="info" :style="{ marginRight: '6px' }" />
                    No purchase history found for this product.
                  </div>

                  <div v-else>
                    <div :style="{ overflowX: 'auto' }">
                      <table :style="tableStyle">
                        <thead>
                          <tr>
                            <th :style="thStyle">{{ $t('date') || 'Date' }}</th>
                            <th :style="thStyle">{{ $t('Reference') || 'Reference' }}</th>
                            <th :style="thStyle">{{ $t('Supplier') || 'Supplier' }}</th>
                            <th :style="thStyle">{{ $t('Warehouse') || 'Warehouse' }}</th>
                            <th :style="thStyle">{{ $t('Quantity') || 'Quantity' }}</th>
                            <th :style="thStyle">{{ $t('Cost') || 'Cost' }}</th>
                            <th :style="thStyle">{{ $t('Total') || 'Total' }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="p in purchasesHistory" :key="p.id" :style="trHover">
                            <td :style="tdStyle">{{ formatDisplayDate(p.date) }}</td>
                            <td :style="tdStyle">
                              <router-link :to="'/app/purchases/detail/' + p.purchase_id" :style="{ color: 'var(--primary-color, #4f46e5)', fontWeight: '700' }">
                                {{ p.ref }}
                              </router-link>
                            </td>
                            <td :style="tdStyle">{{ p.provider_name }}</td>
                            <td :style="tdStyle">{{ p.warehouse_name }}</td>
                            <td :style="tdStyle">{{ formatNumber(p.quantity, 2) }} {{ p.unit }}</td>
                            <td :style="tdStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, p.cost, 2) }}</td>
                            <td :style="tdStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, p.total, 2) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="purchasesHistory.length && purchasesTotalRows > purchasesPerPage" class="d-flex justify-content-between align-items-center mt-3">
                      <div :style="{ color: pdTheme.keyColor, fontSize: '13px' }">
                        Showing {{ (purchasesCurrentPage - 1) * purchasesPerPage + 1 }} to {{ Math.min(purchasesCurrentPage * purchasesPerPage, purchasesTotalRows) }} of {{ purchasesTotalRows }}
                      </div>
                      <b-pagination
                        v-model="purchasesCurrentPage"
                        :total-rows="purchasesTotalRows"
                        :per-page="purchasesPerPage"
                        @change="handlePurchasesPageChange"
                        align="right"
                        size="sm"
                        class="my-0"
                      ></b-pagination>
                    </div>
                  </div>
                </div>
              </b-tab>
            </b-tabs>
          </div>
        </div>

        <!-- Serial Numbers Tracking -->
        <div :style="cardStyle" v-if="product.enable_serial_tracking">
          <div class="pd-card-header" :style="cardHeaderStyle">
            <lucide-icon name="scan-barcode" :style="{ marginRight: '8px', color: '#10b981' }" />
            {{ $t('Serial_Numbers') || 'Serial Numbers Tracking' }}
            <div :style="{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }">
              <span :style="{ background: '#10b981', color: '#fff', fontSize: '12px', padding: '2px 10px', borderRadius: '999px', fontWeight: '600' }">
                {{ serialTotalRows }} {{ $t('items') || 'items' }}
              </span>
              <span v-for="(count, status) in serialStatusCounts" :key="status" :style="serialStatusStyle(status)">
                {{ status }}: {{ count }}
              </span>
            </div>
          </div>

          <div :style="{ padding: '14px 20px 0 20px', display: 'flex', gap: '10px', flexWrap: 'wrap' }">
            <input
              v-model="serialSearch"
              @input="debouncedLoadSerialNumbers"
              :placeholder="$t('Search_serial_number') || 'Search serial number'"
              :style="{ flex: '1 1 240px', minHeight: '36px', border: '1px solid ' + pdTheme.cardBorder, borderRadius: '6px', padding: '8px 10px', background: pdTheme.cardBg, color: pdTheme.valueColor }"
            />
            <select
              v-model="serialStatusFilter"
              @change="loadSerialNumbers"
              :style="{ minHeight: '36px', border: '1px solid ' + pdTheme.cardBorder, borderRadius: '6px', padding: '8px 10px', background: pdTheme.cardBg, color: pdTheme.valueColor }"
            >
              <option value="">{{ $t('All') || 'All' }}</option>
              <option value="available">{{ $t('available') || 'available' }}</option>
              <option value="sold">{{ $t('sold') || 'sold' }}</option>
              <option value="in_service">{{ $t('in_service') || 'in_service' }}</option>
              <option value="returned_to_supplier">{{ $t('returned_to_supplier') || 'returned_to_supplier' }}</option>
              <option value="adjusted_out">{{ $t('adjusted_out') || 'adjusted_out' }}</option>
            </select>
          </div>

          <div v-if="serialsLoading" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor }">
            <div class="spinner spinner-primary" :style="{ display: 'inline-block', marginRight: '10px' }"></div>
            {{ $t('Loading') || 'Loading...' }}
          </div>

          <div v-else-if="!serialNumbers.length" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor, fontStyle: 'italic' }">
            <lucide-icon name="info" :style="{ marginRight: '6px' }" />
            {{ $t('No_serial_numbers_found') || 'No serial numbers found for this product.' }}
          </div>

          <div v-else :style="{ padding: '8px 20px 20px 20px', overflowX: 'auto' }">
            <table :style="tableStyle">
              <thead>
                <tr>
                  <th :style="thStyle">{{ $t('Serial_Number') || 'Serial Number' }}</th>
                  <th :style="thStyle">{{ $t('Status') || 'Status' }}</th>
                  <th :style="thStyle">{{ $t('Location') || 'Location' }}</th>
                  <th :style="thStyle">{{ $t('Customer') || 'Customer' }}</th>
                  <th :style="thStyle">{{ $t('Mobile') || 'Mobile' }}</th>
                  <th :style="thStyle">{{ $t('Warranty') || 'Warranty' }}</th>
                  <th :style="thStyle">{{ $t('Last_Activity') || 'Last Activity' }}</th>
                  <th :style="{ ...thStyle, textAlign: 'center' }">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in serialNumbers" :key="s.id" :style="trHover">
                  <td :style="tdStyle">
                    <a href="#" @click.prevent="showSerialHistory(s)" :style="{ color: '#10b981', fontWeight: '800', textDecoration: 'underline' }">
                      {{ s.serial_no }}
                    </a>
                  </td>
                  <td :style="tdStyle"><span :style="serialStatusStyle(s.status)">{{ s.status || 'available' }}</span></td>
                  <td :style="tdStyle">{{ s.warehouse || '—' }}</td>
                  <td :style="tdStyle">{{ s.customer || '—' }}</td>
                  <td :style="tdStyle">
                    <span v-if="s.customer_phone" :style="{ color: '#10b981', fontWeight: '600' }">
                      <lucide-icon name="phone" style="width:12px;height:12px;margin-right:3px;vertical-align:middle;" />
                      {{ s.customer_phone }}
                    </span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                  </td>
                  <td :style="tdStyle">{{ s.warranty_expiry ? formatDisplayDate(s.warranty_expiry) : '—' }}</td>
                  <td :style="tdStyle">
                    <span v-if="s.last_activity">{{ s.last_activity.notes }}</span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                  </td>
                  <td :style="{ ...tdStyle, textAlign: 'center' }">
                    <button
                      class="btn btn-sm btn-link text-primary p-0"
                      @click="showSerialHistory(s)"
                      title="View Timeline"
                    >
                      <lucide-icon name="clock" style="width: 16px; height: 16px;" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="serialNumbers.length && serialTotalRows > serialPerPage" class="d-flex justify-content-between align-items-center px-4 pb-3">
            <div :style="{ color: pdTheme.keyColor, fontSize: '13px' }">
              Showing {{ (serialCurrentPage - 1) * serialPerPage + 1 }} to {{ Math.min(serialCurrentPage * serialPerPage, serialTotalRows) }} of {{ serialTotalRows }}
            </div>
            <b-pagination
              v-model="serialCurrentPage"
              :total-rows="serialTotalRows"
              :per-page="serialPerPage"
              @change="handleSerialPageChange"
              align="right"
              size="sm"
              class="my-0"
            ></b-pagination>
          </div>
        </div>

        <!-- Serial Timeline History Modal -->
        <b-modal
          v-model="serialHistoryModalOpen"
          :title="'Timeline / History - ' + (selectedSerial ? selectedSerial.serial_no : '')"
          hide-footer
          centered
          size="lg"
          :header-bg-variant="isDarkMode ? 'dark' : 'primary'"
          header-text-variant="light"
          body-class="p-0"
        >
          <div :style="timelineContainerStyle">
            <div v-if="serialHistoryLoading" class="text-center p-5" :style="{ color: pdTheme.valueColor }">
              <div class="spinner spinner-primary d-inline-block mr-2"></div>
              Loading timeline history...
            </div>
            <div v-else-if="!serialHistory.length" class="text-center p-5 italic" :style="{ color: pdTheme.mutedColor }">
              No history records found for this serial number.
            </div>
            <div v-else class="timeline-wrapper p-4">
              <div v-for="(item, idx) in serialHistory" :key="item.id" class="timeline-item d-flex">
                <div class="timeline-left d-flex flex-column align-items-center mr-3">
                  <div class="timeline-badge" :style="timelineBadgeStyle(item.type)">
                    <lucide-icon :name="timelineIconName(item.type)" style="width: 14px; height: 14px;" />
                  </div>
                  <div v-if="idx < serialHistory.length - 1" class="timeline-line" :style="timelineLineStyle"></div>
                </div>
                <div class="timeline-content pb-4 flex-grow-1">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <h6 class="timeline-title m-0" :style="{ fontWeight: '700', textTransform: 'uppercase', color: pdTheme.cardHeaderText, fontSize: '14px' }">{{ item.type }}</h6>
                    <span class="timeline-date" :style="{ fontSize: '12px', color: pdTheme.keyColor, fontWeight: '500' }">{{ item.created_at }}</span>
                  </div>
                  <p v-if="item.notes" class="timeline-notes my-2" :style="{ fontSize: '13px', color: pdTheme.valueColor, fontWeight: '500', lineHeight: '1.5' }">{{ item.notes }}</p>
                  <div class="timeline-meta mt-1" :style="{ fontSize: '12px', color: pdTheme.keyColor, lineHeight: '1.6' }">
                    <span v-if="item.from_location">From Location: <strong :style="{ color: pdTheme.valueColor, fontWeight: '700' }">{{ item.from_location }}</strong> &nbsp;|&nbsp; </span>
                    <span v-if="item.to_location">To Location: <strong :style="{ color: pdTheme.valueColor, fontWeight: '700' }">{{ item.to_location }}</strong> &nbsp;|&nbsp; </span>
                    <span v-if="item.customer">Customer: <strong :style="{ color: pdTheme.valueColor, fontWeight: '700' }">{{ item.customer }}</strong>
                      <span v-if="item.customer_phone" style="color: #10b981; margin-left: 4px; font-weight: 600;">
                        <lucide-icon name="phone" style="width:11px;height:11px;vertical-align:middle;margin-right:2px;" />{{ item.customer_phone }}
                      </span>
                      &nbsp;|&nbsp;
                    </span>
                    <span v-if="item.created_by">Logged By: <strong :style="{ color: pdTheme.valueColor, fontWeight: '700' }">{{ item.created_by }}</strong></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </b-modal>

        <!-- Product Image Gallery Modal -->
        <b-modal
          v-model="galleryModalOpen"
          title="Product Image Gallery"
          hide-footer
          centered
          size="lg"
        >
          <div class="text-center p-3">
            <div
              :style="{
                width: '100%',
                height: '420px',
                borderRadius: '12px',
                overflow: 'hidden',
                background: pdTheme.galleryFrameBg,
                border: `1px solid ${pdTheme.galleryFrameBorder}`,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: '16px'
              }"
            >
              <img
                :src="'/images/products/' + activeImage"
                :alt="product.name"
                :style="{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }"
                @error="onImgError"
              />
            </div>

            <div
              :style="{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))',
                gap: '10px',
                justifyContent: 'center'
              }"
            >
              <div
                v-for="(img, idx) in productImages"
                :key="idx"
                @click="activeImageIndex = idx"
                :style="{
                  paddingTop: '100%',
                  position: 'relative',
                  borderRadius: '10px',
                  overflow: 'hidden',
                  cursor: 'pointer',
                  border: activeImageIndex === idx ? '3px solid #6d28d9' : '3px solid transparent',
                  boxShadow: activeImageIndex === idx ? '0 4px 12px rgba(109,40,217,0.25)' : 'none',
                  transition: 'all 0.2s'
                }"
              >
                <img
                  :src="'/images/products/' + img"
                  :style="{
                    position: 'absolute',
                    top: 0, left: 0,
                    width: '100%', height: '100%',
                    objectFit: 'cover'
                  }"
                  @error="onImgError"
                />
              </div>
            </div>
          </div>
        </b-modal>

        <!-- Barcode Preview Modal -->
        <b-modal
          v-model="barcodeModalOpen"
          title="Barcode Preview"
          hide-footer
          centered
          size="sm"
        >
          <div class="text-center p-4">
            <barcode
              class="barcode"
              :format="['CODE128', 'CODE39', 'EAN13', 'EAN8', 'UPCA', 'UPCE', 'ITF', 'MSI', 'PHARMACODE'].includes(String(product.Type_barcode).toUpperCase()) ? String(product.Type_barcode).toUpperCase() : 'CODE128'"
              :value="product.code"
              textmargin="0"
              fontoptions="bold"
            ></barcode>
            <div class="mt-3 text-muted small">
              Symbology: <strong>{{ product.Type_barcode }}</strong>
            </div>
          </div>
        </b-modal>
      </div>
    </div>
  </div>
</template>

<script>
import VueBarcode from "vue-barcode";
import { mapActions, mapGetters } from "vuex";
import Util from "../../../../utils/index";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Detail Product"
  },
  components: {
    barcode: VueBarcode
  },

  data() {
    return {
      len: 8,
      isLoading: true,
      product: {},
      roles: {},
      variants: [],
      activeImageIndex: 0,
      price_format_key: null,

      // Modals
      galleryModalOpen: false,
      barcodeModalOpen: false,

      // Pharmacy
      batches: [],
      batchesLoading: false,
      expiryWarningDays: 90,

      serialNumbers: [],
      serialsLoading: false,
      serialSearch: "",
      serialStatusFilter: "",
      serialSearchTimer: null,

      // Pagination variables
      serialCurrentPage: 1,
      serialPerPage: 10,
      serialTotalRows: 0,

      // Modal variables
      serialHistoryModalOpen: false,
      selectedSerial: null,
      serialHistoryLoading: false,
      serialHistory: [],

      // Sales History
      salesHistory: [],
      salesLoading: false,
      salesSearch: "",
      salesSearchTimer: null,
      salesCurrentPage: 1,
      salesPerPage: 10,
      salesTotalRows: 0,

      // Purchases History
      purchasesHistory: [],
      purchasesLoading: false,
      purchasesSearch: "",
      purchasesSearchTimer: null,
      purchasesCurrentPage: 1,
      purchasesPerPage: 10,
      purchasesTotalRows: 0
    };
  },

  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions"]),
    ...mapGetters("config", ["getThemeMode"]),

    profitMargin() {
      const cost = parseFloat(this.product.cost || 0);
      const price = parseFloat(this.product.price || 0);
      if (cost <= 0) return 0;
      return Math.round(((price - cost) / cost) * 100);
    },

    timelineContainerStyle() {
      return {
        background: this.isDarkMode ? '#202020' : '#ffffff',
        color: this.pdTheme.valueColor,
        minHeight: '300px',
        maxHeight: '500px',
        overflowY: 'auto'
      };
    },
    timelineLineStyle() {
      return {
        width: '2px',
        background: this.isDarkMode ? '#444' : '#e9ecef',
        flexGrow: 1,
        marginTop: '4px',
        marginBottom: '4px'
      };
    },

    isDarkMode() {
      return !!(this.getThemeMode && this.getThemeMode.dark);
    },

    pdTheme() {
      return this.isDarkMode ? {
        pageBg:           'linear-gradient(135deg, #1a1a1a 0%, #202020 100%)',
        cardBg:           '#202020',
        cardBorder:       '#292929',
        cardShadow:       '0 2px 12px rgba(0,0,0,0.4)',
        cardHeaderBg:     'linear-gradient(180deg, #292929 0%, #202020 100%)',
        cardHeaderText:   '#d8d8d8',
        keyColor:         'rgba(216,216,216,0.7)',
        valueColor:       '#d8d8d8',
        labelColor:       'rgba(216,216,216,0.7)',
        mutedColor:       'rgba(216,216,216,0.5)',
        dashedBorder:     '#292929',
        tableHeaderBg:    '#292929',
        tableHeaderText:  'rgba(216,216,216,0.8)',
        tableHeaderRule:  '#292929',
        tableRowRule:     '#292929',
        tableCellText:    '#d8d8d8',
        codeBg:           '#292929',
        codeText:         '#a78bfa',
        noteBg:           '#292929',
        noteText:         'rgba(216,216,216,0.85)',
        warehouseCardBg:  'linear-gradient(135deg, rgba(14,165,233,0.12) 0%, rgba(14,165,233,0.05) 100%)',
        warehouseCardBorder: 'rgba(14,165,233,0.35)',
        warehouseLabel:   'rgba(216,216,216,0.7)',
        warehouseValue:   '#bae6fd',
        warehouseUnit:    'rgba(216,216,216,0.6)',
        galleryFrameBg:   '#292929',
        galleryFrameBorder: '#292929',
        thumbInnerBg:     '#292929',
        backBtnBg:        'transparent',
        backBtnColor:     '#d8d8d8',
        backBtnBorder:    'rgba(216,216,216,0.25)',
        backBtnHoverBg:   'rgba(216,216,216,0.08)',
        backBtnHoverFg:   '#a78bfa'
      } : {
        pageBg:           'linear-gradient(135deg, #f8f9fc 0%, #eef2f7 100%)',
        cardBg:           '#ffffff',
        cardBorder:       '#eef2f7',
        cardShadow:       '0 2px 12px rgba(15,23,42,0.06)',
        cardHeaderBg:     'linear-gradient(180deg, #fafbff 0%, #ffffff 100%)',
        cardHeaderText:   '#1e293b',
        keyColor:         '#64748b',
        valueColor:       '#0f172a',
        labelColor:       '#64748b',
        mutedColor:       '#94a3b8',
        dashedBorder:     '#e5e7eb',
        tableHeaderBg:    '#f8fafc',
        tableHeaderText:  '#64748b',
        tableHeaderRule:  '#e5e7eb',
        tableRowRule:     '#f1f5f9',
        tableCellText:    '#1e293b',
        codeBg:           '#f1f5f9',
        codeText:         '#4f46e5',
        noteBg:           '#f8fafc',
        noteText:         '#475569',
        warehouseCardBg:  'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)',
        warehouseCardBorder: '#bae6fd',
        warehouseLabel:   '#64748b',
        warehouseValue:   '#0c4a6e',
        warehouseUnit:    '#64748b',
        galleryFrameBg:   '#f8fafc',
        galleryFrameBorder: '#e5e7eb',
        thumbInnerBg:     '#fff',
        backBtnBg:        'transparent',
        backBtnColor:     '#475569',
        backBtnBorder:    '#cbd5e1',
        backBtnHoverBg:   '#fff',
        backBtnHoverFg:   '#4f46e5'
      };
    },

    rootStyle() {
      return {
        background: this.pdTheme.pageBg,
        padding: '24px',
        borderRadius: '12px',
        minHeight: '100vh'
      };
    },
    cardStyle() {
      return {
        background: this.pdTheme.cardBg,
        borderRadius: '14px',
        boxShadow: this.pdTheme.cardShadow,
        border: `1px solid ${this.pdTheme.cardBorder}`,
        marginBottom: '20px',
        overflow: 'hidden'
      };
    },
    cardHeaderStyle() {
      return {
        padding: '16px 20px',
        borderBottom: `1px solid ${this.pdTheme.cardBorder}`,
        fontSize: '15px',
        fontWeight: '700',
        color: this.pdTheme.cardHeaderText,
        display: 'flex',
        alignItems: 'center',
        background: this.pdTheme.cardHeaderBg
      };
    },

    // Summary Card styling
    summaryCardStyle() {
      return {
        background: this.pdTheme.cardBg,
        borderRadius: '14px',
        border: `1px solid ${this.pdTheme.cardBorder}`,
        boxShadow: this.pdTheme.cardShadow,
        padding: '20px',
        display: 'flex',
        flexDirection: 'column'
      };
    },
    summaryCardHeaderTitleStyle() {
      return {
        fontSize: '14px',
        fontWeight: '700',
        color: this.pdTheme.cardHeaderText,
        margin: 0
      };
    },
    summaryCardRowStyle() {
      return {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        borderBottom: `1px dashed ${this.pdTheme.dashedBorder}`,
        paddingBottom: '8px',
        fontSize: '13px'
      };
    },
    summaryCardLabelStyle() {
      return {
        color: this.pdTheme.keyColor,
        fontWeight: '500'
      };
    },
    summaryCardValueStyle() {
      return {
        color: this.pdTheme.valueColor,
        fontWeight: '700'
      };
    },

    // Spec rows style
    specRowStyle() {
      return {
        borderBottom: `1px dashed ${this.pdTheme.dashedBorder}`
      };
    },
    specLabelStyle() {
      return {
        padding: '12px 0',
        fontSize: '13px',
        color: this.pdTheme.keyColor,
        fontWeight: '500'
      };
    },
    specValueStyle() {
      return {
        padding: '12px 0',
        fontSize: '13px',
        color: this.pdTheme.valueColor,
        fontWeight: '700'
      };
    },

    tableStyle() {
      return { width: '100%', borderCollapse: 'separate', borderSpacing: '0', fontSize: '14px' };
    },
    thStyle() {
      return {
        padding: '10px 12px',
        textAlign: 'left',
        color: this.pdTheme.tableHeaderText,
        fontWeight: '700',
        fontSize: '12px',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        borderBottom: `2px solid ${this.pdTheme.tableHeaderRule}`,
        background: this.pdTheme.tableHeaderBg
      };
    },
    tdStyle() {
      return {
        padding: '12px',
        borderBottom: `1px solid ${this.pdTheme.tableRowRule}`,
        color: this.pdTheme.tableCellText
      };
    },
    trHover() { return { transition: 'background 0.2s' }; },

    printBtnStyle() {
      return {
        background: 'linear-gradient(135deg, var(--primary-color, #4f46e5) 0%, var(--primary-color-darker, #7c3aed) 100%)',
        color: '#fff',
        border: 'none',
        padding: '10px 20px',
        borderRadius: '10px',
        fontWeight: '600',
        fontSize: '14px',
        cursor: 'pointer',
        boxShadow: '0 4px 10px rgba(79,70,229,0.25)',
        transition: 'all 0.2s',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },
    backBtnStyle() {
      return {
        background: this.pdTheme.backBtnBg,
        color: this.pdTheme.backBtnColor,
        border: `1px solid ${this.pdTheme.backBtnBorder}`,
        padding: '10px 18px',
        borderRadius: '10px',
        fontWeight: '600',
        fontSize: '14px',
        cursor: 'pointer',
        transition: 'all 0.2s',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },

    categoriesLine() {
      const p = this.product;
      if (!p || typeof p !== "object") return "";
      if (Array.isArray(p.categories) && p.categories.length) {
        return p.categories.map(c => c && c.name).filter(Boolean).join(", ");
      }
      return p.category || "";
    },
    subcategoriesLine() {
      const p = this.product;
      if (!p || typeof p !== "object") return "";
      if (Array.isArray(p.subcategories) && p.subcategories.length) {
        return p.subcategories.map(s => s && s.name).filter(Boolean).join(", ");
      }
      return p.sub_category || "";
    },
    productImages() {
      const p = this.product;
      if (!p || typeof p !== "object") return [];
      if (Array.isArray(p.images) && p.images.length) {
        return p.images.filter(Boolean);
      }
      if (typeof p.image === "string" && p.image.trim() !== "") {
        return p.image.split(",").map(s => s.trim()).filter(Boolean);
      }
      return [];
    },
    activeImage() {
      const imgs = this.productImages;
      if (!imgs.length) return this.product.image || 'no-image.png';
      return imgs[Math.min(this.activeImageIndex, imgs.length - 1)];
    },
    totalStock() {
      if (!this.product || !Array.isArray(this.product.CountQTY)) return 0;
      return this.product.CountQTY.reduce((sum, w) => sum + (parseFloat(w.qte) || 0), 0);
    },
    serialStatusCounts() {
      return (this.serialNumbers || []).reduce((counts, row) => {
        const status = row.status || 'available';
        counts[status] = (counts[status] || 0) + 1;
        return counts;
      }, {});
    }
  },

  methods: {
    formatDisplayDate(value) {
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    goBack() {
      this.$router.go(-1);
    },

    onImgError(e) {
      e.target.src = '/images/products/no-image.png';
    },

    heroBadge(bg) {
      return {
        background: bg,
        color: '#fff',
        padding: '5px 12px',
        borderRadius: '20px',
        fontSize: '11px',
        fontWeight: '600',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },

    summaryCardIconWrapperStyle(color, bg) {
      return {
        width: '36px',
        height: '36px',
        borderRadius: '10px',
        background: bg,
        color: color,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0
      };
    },

    onBackBtnHover(e, isHover) {
      const t = this.pdTheme;
      e.currentTarget.style.background = isHover ? t.backBtnHoverBg : t.backBtnBg;
      e.currentTarget.style.color      = isHover ? t.backBtnHoverFg : t.backBtnColor;
    },

    formatNumber(number, dec) {
      if (number === null || number === undefined) number = 0;
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) this.price_format_key = key;
        return formatPriceDisplayHelper(number, decimals, key);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    print_product() {
      const el = document.getElementById('print_product');
      if (!el) return;

      const win = window.open('', '_blank', 'fullscreen=yes,titlebar=yes,scrollbars=yes');
      if (!win) {
        alert('Please allow pop-ups to print this page.');
        return;
      }

      const title = (this.product && this.product.name)
        ? `${this.product.name} — ${this.product.code || ''}`
        : document.title;

      const html = `<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${title}</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" />
    <style>
      html, body { margin: 0; padding: 0; background: #fff; color: #0f172a; font-family: 'Segoe UI', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      body { padding: 20px; }
      table { page-break-inside: auto; }
      tr    { page-break-inside: avoid; page-break-after: auto; }
      img   { max-width: 100%; height: auto; }
      @page { margin: 10mm; }
      @media print {
        body { padding: 0; }
      }
    </style>
  </head>
  <body>${el.innerHTML}</body>
</html>`;

      win.document.open();
      win.document.write(html);
      win.document.close();

      const doPrint = () => {
        try {
          win.focus();
          const closeAfter = () => { try { win.close(); } catch (e) {} };
          if (typeof win.onafterprint !== 'undefined') {
            win.onafterprint = closeAfter;
          }
          win.print();
          setTimeout(closeAfter, 1500);
        } catch (e) {
          try { win.close(); } catch (_) {}
        }
      };

      const waitForImages = () => {
        const imgs = Array.from(win.document.images || []);
        if (!imgs.length) return Promise.resolve();
        return Promise.all(
          imgs.map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise(res => {
              img.addEventListener('load', res, { once: true });
              img.addEventListener('error', res, { once: true });
            });
          })
        );
      };

      if (win.document.readyState === 'complete') {
        waitForImages().then(doPrint);
      } else {
        win.addEventListener('load', () => waitForImages().then(doPrint));
      }
    },

    showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail_api/${id}`)
        .then(response => {
          this.product = response.data;
          this.isLoading = false;

          this.loadSalesHistory();
          this.loadPurchasesHistory();

          if (this.product && this.product.enable_serial_tracking) {
            this.loadSerialNumbers();

            if (this.$route.query.search_serial_id) {
              this.showSerialHistory({ id: this.$route.query.search_serial_id });
            }
          }
        })
        .catch(() => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    debouncedLoadSalesHistory() {
      if (this.salesSearchTimer) {
        clearTimeout(this.salesSearchTimer);
      }
      this.salesSearchTimer = setTimeout(() => {
        this.salesCurrentPage = 1;
        this.loadSalesHistory();
      }, 250);
    },

    loadSalesHistory() {
      const id = this.$route.params.id;
      if (!id) return;
      this.salesLoading = true;
      axios
        .get(`products/${id}/sales_history`, {
          params: {
            search: this.salesSearch || undefined,
            limit: this.salesPerPage,
            page: this.salesCurrentPage
          }
        })
        .then(response => {
          const data = response && response.data ? response.data : {};
          this.salesHistory = Array.isArray(data.history) ? data.history : [];
          this.salesTotalRows = data.total || 0;
        })
        .catch(() => {
          this.salesHistory = [];
          this.salesTotalRows = 0;
        })
        .then(() => {
          this.salesLoading = false;
        });
    },

    handleSalesPageChange(page) {
      this.salesCurrentPage = page;
      this.loadSalesHistory();
    },

    debouncedLoadPurchasesHistory() {
      if (this.purchasesSearchTimer) {
        clearTimeout(this.purchasesSearchTimer);
      }
      this.purchasesSearchTimer = setTimeout(() => {
        this.purchasesCurrentPage = 1;
        this.loadPurchasesHistory();
      }, 250);
    },

    loadPurchasesHistory() {
      const id = this.$route.params.id;
      if (!id) return;
      this.purchasesLoading = true;
      axios
        .get(`products/${id}/purchases_history`, {
          params: {
            search: this.purchasesSearch || undefined,
            limit: this.purchasesPerPage,
            page: this.purchasesCurrentPage
          }
        })
        .then(response => {
          const data = response && response.data ? response.data : {};
          this.purchasesHistory = Array.isArray(data.history) ? data.history : [];
          this.purchasesTotalRows = data.total || 0;
        })
        .catch(() => {
          this.purchasesHistory = [];
          this.purchasesTotalRows = 0;
        })
        .then(() => {
          this.purchasesLoading = false;
        });
    },

    handlePurchasesPageChange(page) {
      this.purchasesCurrentPage = page;
      this.loadPurchasesHistory();
    },

    debouncedLoadSerialNumbers() {
      if (this.serialSearchTimer) {
        clearTimeout(this.serialSearchTimer);
      }
      this.serialSearchTimer = setTimeout(() => this.loadSerialNumbers(), 250);
    },

    loadSerialNumbers() {
      const id = this.$route.params.id;
      if (!id) return;
      this.serialsLoading = true;
      axios
        .get(`products/${id}/serial_numbers`, {
          params: {
            search: this.serialSearch || undefined,
            status: this.serialStatusFilter || undefined,
            limit: this.serialPerPage,
            page: this.serialCurrentPage,
          },
        })
        .then(response => {
          const data = response && response.data ? response.data : {};
          this.serialNumbers = Array.isArray(data.serials) ? data.serials : [];
          this.serialTotalRows = data.total || 0;
        })
        .catch(() => {
          this.serialNumbers = [];
          this.serialTotalRows = 0;
        })
        .then(() => {
          this.serialsLoading = false;
        });
    },

    handleSerialPageChange(page) {
      this.serialCurrentPage = page;
      this.loadSerialNumbers();
    },

    showSerialHistory(serial) {
      this.selectedSerial = serial;
      this.serialHistoryLoading = true;
      this.serialHistoryModalOpen = true;
      this.serialHistory = [];
      axios.get(`/products/serials/${serial.id}/history`)
        .then(response => {
          this.serialHistory = response.data.histories || [];
        })
        .catch(() => {
          this.serialHistory = [];
        })
        .then(() => {
          this.serialHistoryLoading = false;
        });
    },

    timelineBadgeStyle(type) {
      let bg = '#6c757d';
      if (type === 'purchase') bg = '#28a745';
      else if (type === 'sell') bg = '#007bff';
      else if (type === 'transfer') bg = '#fd7e14';
      else if (type === 'service') bg = '#17a2b8';
      else if (type === 'adjustment') bg = '#6f42c1';
      else if (type === 'damage') bg = '#dc3545';
      else if (type === 'sale_return') bg = '#e83e8c';
      
      return {
        background: bg,
        color: '#fff',
        width: '28px',
        height: '28px',
        borderRadius: '50%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 2px 5px rgba(0,0,0,0.2)'
      };
    },

    timelineIconName(type) {
      if (type === 'purchase') return 'shopping-cart';
      if (type === 'sell') return 'dollar-sign';
      if (type === 'transfer') return 'move';
      if (type === 'service') return 'wrench';
      if (type === 'adjustment') return 'sliders';
      if (type === 'damage') return 'alert-triangle';
      if (type === 'sale_return') return 'rotate-ccw';
      return 'clock';
    },

    serialStatusStyle(status) {
      const colors = {
        available: '#10b981',
        sold: '#ef4444',
        in_service: '#f59e0b',
        returned_to_supplier: '#64748b',
        adjusted_out: '#7c3aed',
      };
      const color = colors[status] || '#0ea5e9';
      return {
        background: color,
        color: '#fff',
        fontSize: '11px',
        padding: '2px 8px',
        borderRadius: '20px',
        fontWeight: '700',
        display: 'inline-block',
      };
    },
  },

  created: function() {
    this.showDetails();
  },

  watch: {
    '$route.query.search_serial_id'(newSerialId) {
      if (newSerialId) {
        this.showSerialHistory({ id: newSerialId });
      }
    }
  }
};
</script>

<style scoped>
/* Custom styled Tabs */
::v-deep .custom-nav-tabs {
  border-bottom: 2px solid #eef2f7;
  gap: 6px;
}
::v-deep .custom-nav-tabs .nav-link {
  border: none !important;
  color: #64748b;
  font-weight: 600;
  font-size: 13px;
  padding: 10px 18px;
  border-radius: 8px 8px 0 0;
  transition: all 0.2s;
}
::v-deep .custom-nav-tabs .nav-link:hover {
  color: var(--primary-color, #4f46e5);
  background-color: #f8fafc;
}
::v-deep .custom-nav-tabs .nav-link.active {
  color: var(--primary-color, #4f46e5) !important;
  background-color: #f8fafc !important;
  border-bottom: 3px solid var(--primary-color, #4f46e5) !important;
}

/* Specs Table */
.table-specs tr {
  border-bottom: 1px dashed #e2e8f0;
}
.table-specs tr:last-child {
  border-bottom: none;
}

/* Stack layout responsive options */
@media (max-width: 992px) {
  .pd-main-grid {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
}

/* Mobile / phone styling updates */
@media (max-width: 768px) {
  .pd-root {
    padding: 12px !important;
    border-radius: 8px !important;
  }

  .pd-actions {
    flex-wrap: wrap !important;
    justify-content: stretch !important;
    gap: 8px !important;
  }
  .pd-actions > button, .pd-actions > a {
    flex: 1 1 auto !important;
    justify-content: center !important;
    padding: 10px 14px !important;
    font-size: 13px !important;
  }

  .pd-hero {
    padding: 20px !important;
    border-radius: 14px !important;
  }
  .pd-hero-row {
    gap: 16px !important;
  }
  .pd-hero-img {
    width: 110px !important;
    height: 110px !important;
  }
  .pd-hero-title {
    font-size: 22px !important;
  }
  .pd-hero-price {
    width: 100% !important;
    min-width: 0 !important;
    padding: 12px 16px !important;
  }

  /* Card headers wrap on mobile */
  .pd-card-header {
    flex-wrap: wrap !important;
    gap: 6px !important;
    padding: 12px 14px !important;
    font-size: 14px !important;
  }
  .pd-card-header > div {
    margin-left: 0 !important;
    width: 100% !important;
    justify-content: flex-start !important;
  }
}

/* Extra-small devices */
@media (max-width: 480px) {
  .pd-root {
    padding: 8px !important;
  }
  .pd-hero {
    padding: 16px !important;
  }
  .pd-hero-img {
    width: 90px !important;
    height: 90px !important;
  }
  .pd-hero-title {
    font-size: 18px !important;
  }
}
</style>
