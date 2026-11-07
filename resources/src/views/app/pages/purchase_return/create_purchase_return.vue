<template>
  <div class="main-content">
    <breadcumb :page="$t('CreatePurchaseReturn')" :folder="$t('ListReturns')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="create_Return" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Return_Purchase">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>
                 <!-- date  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="date-feedback"
                        type="date"
                        v-model="purchase_return.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Purchase -->
               <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-form-group :label="$t('Purchase')">
                    <b-form-input
                      label="Purchase"
                      v-model="purchase_return.purchase_ref"
                      disabled="disabled"
                    ></b-form-input>
                  </b-form-group>
                </b-col>

                  <!-- Status  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Status" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="purchase_return.statut"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="
                            [
                              {label: 'completed', value: 'completed'},
                              {label: 'pending', value: 'pending'},
                            ]"
                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Order products  -->
                <b-col md="12">
                  <h5>{{$t('list_product_returns')}} *</h5>
                  <div class="alert alert-danger">{{$t('products_refunded_alert')}}</div>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                          <th scope="col">{{$t('qty_purchased')}}</th>
                          <th scope="col">{{$t('Current_stock')}}</th>
                          <th scope="col">{{$t('Qty_return')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <= 0">
                          <td colspan="9">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <template v-for="detail in details">
                          <tr :key="'row-'+detail.detail_id">
                            <td>{{detail.detail_id}}</td>
                            <td>
                              <span>{{detail.code}}</span>
                              <br>
                              <span class="badge badge-success">{{detail.name}}</span>
                              <div v-if="detail.is_batch_tracked" class="mt-1">
                                <span
                                  class="badge"
                                  style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;"
                                  :title="$t('Auto_FEFO_Hint') || 'Oldest-expiring batches will be auto-allocated (FEFO) when this return is completed.'"
                                >
                                  <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') || 'Batches' }} · FEFO
                                </span>
                              </div>
                            </td>

                            <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                            <td>
                              <span
                                class="badge badge-outline-warning"
                              >{{detail.purchase_quantity}} {{detail.unitPurchase}}</span>
                            </td>
                             <td>
                              <span
                                class="badge badge-outline-warning"
                              >{{detail.stock}} {{detail.unitPurchase}}</span>
                            </td>

                            <td>
                              <div class="quantity">
                                <b-input-group>
                                  <b-input-group-prepend>
                                    <span
                                      class="btn btn-primary btn-sm"
                                      @click="decrement(detail ,detail.detail_id)"
                                    >-</span>
                                  </b-input-group-prepend>
                                  <input
                                    class="form-control"
                                    @keyup="Verified_Qty(detail,detail.detail_id)"
                                    :min="0.00"
                                    v-model.number="detail.quantity"
                                  >
                                  <b-input-group-append>
                                    <span
                                      class="btn btn-primary btn-sm"
                                      @click="increment(detail ,detail.detail_id)"
                                    >+</span>
                                  </b-input-group-append>
                                </b-input-group>
                              </div>
                            </td>
                            <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                            <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity , 2)}}</td>
                            <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          </tr>

                          <!-- Serial numbers row for serial-tracked products -->
                          <tr v-if="detail.enable_serial_tracking" :key="'serials-'+detail.detail_id" style="background: transparent;">
                            <td colspan="9" style="padding: 0; border-top: 0;">
                              <div style="margin: 6px 8px 14px 8px; border: 1px solid #d1fae5; border-radius: 10px; overflow: hidden; background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);">
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                                  <div style="display: flex; align-items: center; gap: 8px;">
                                    <lucide-icon name="list" />
                                    <span>{{ $t('Serial_IMEI_Numbers') || 'Serial/IMEI Numbers' }}</span>
                                    <span style="background: rgba(255,255,255,0.22); padding: 1px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                                      {{ (detail.serial_numbers || []).length }} / {{ Math.floor(detail.quantity) }}
                                    </span>
                                  </div>
                                </div>

                                <div style="padding: 12px 16px;">
                                  <div v-if="detail.available_serials && detail.available_serials.length">
                                    <div style="font-size: 11px; font-weight: bold; color: #4b5563; margin-bottom: 6px;">
                                      {{ $t('Available_Serials_Hint') || 'Available Serials (Click to select/unselect):' }}
                                    </div>
                                    <div class="d-flex flex-wrap gap-1" style="max-height: 100px; overflow-y: auto; padding: 6px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;">
                                      <span
                                        v-for="sn in detail.available_serials"
                                        :key="sn"
                                        @click="toggle_serial_number(detail, sn)"
                                        :style="{
                                          cursor: 'pointer',
                                          fontSize: '11px',
                                          padding: '3px 8px',
                                          borderRadius: '4px',
                                          border: '1px solid',
                                          backgroundColor: (detail.serial_numbers || []).includes(sn) ? '#ecfdf5' : '#f3f4f6',
                                          borderColor: (detail.serial_numbers || []).includes(sn) ? '#10b981' : '#e5e7eb',
                                          color: (detail.serial_numbers || []).includes(sn) ? '#065f46' : '#374151',
                                          marginRight: '6px',
                                          marginBottom: '6px',
                                          display: 'inline-block',
                                          fontWeight: (detail.serial_numbers || []).includes(sn) ? '600' : 'normal'
                                        }"
                                      >
                                        {{ sn }}
                                        <lucide-icon v-if="(detail.serial_numbers || []).includes(sn)" name="check" style="width: 12px; height: 12px; margin-left: 2px;" />
                                      </span>
                                    </div>
                                  </div>
                                  <div v-else class="text-warning" style="font-size: 11px;">
                                    <lucide-icon name="alert-triangle" style="font-size: 13px; margin-right: 4px;" />
                                    {{ $t('No_Available_Serials') || 'No unsold serial numbers available for this product in this purchase.' }}
                                  </div>

                                  <div
                                    v-if="detail.quantity > 0 && (detail.serial_numbers || []).length !== Math.floor(detail.quantity)"
                                    class="text-danger mt-2"
                                    style="font-size: 12px; display: flex; align-items: center; gap: 6px;"
                                  >
                                    <lucide-icon name="alert-circle" />
                                    <span>Please select exactly {{ Math.floor(detail.quantity) }} serial number(s) (Currently selected: {{ (detail.serial_numbers || []).length }})</span>
                                  </div>
                                </div>
                              </div>
                            </td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </b-col>

                <div class="offset-md-9 col-md-3 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{purchase_return.TaxNet.toFixed(2)}} ({{formatNumber(purchase_return.tax_rate ,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>{{currentUser.currency}} {{purchase_return.discount.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{purchase_return.shipping.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span
                            class="font-weight-bold"
                          >{{currentUser.currency}} {{GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                 <!-- Order Tax  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Order Tax"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('OrderTax')">
                      <b-input-group append="%">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="OrderTax-feedback"
                          label="Order Tax"
                          v-model.number="purchase_return.tax_rate"
                          @keyup="keyup_OrderTax()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Discount -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Discount"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Discount')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Discount-feedback"
                          label="Discount"
                          v-model.number="purchase_return.discount"
                          @keyup="keyup_Discount()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Discount-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Shipping  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Shipping"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Shipping')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Shipping-feedback"
                          label="Shipping"
                          v-model.number="purchase_return.shipping"
                          @keyup="keyup_Shipping()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Shipping-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>


                <b-col md="12">
                  <b-form-group :label="$t('Please_provide_any_details')">
                    <textarea
                      v-model="purchase_return.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" @click="Submit_Return_Purchase" :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    <div v-once class="typo__p" v-if="SubmitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

    
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Return Purchase"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      details: [],
      detail: {},
      purchases: [],
      purchase_return: {
        id: "",
        date: new Date().toISOString().slice(0, 10),
        statut: "completed",
        notes: "",
        supplier_id: "",
        warehouse_id: "",
        purchase_id: "",
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0
      },
      total: 0,
      GrandTotal: 0,
    };
  },

  computed: {
    ...mapGetters(["currentUser"])
  },

  methods: {
    
    //--- Submit Validate Create Return Purchase
    Submit_Return_Purchase() {
      this.$refs.create_Return.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Return_Purchase();
        }
      });
    },
   
    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    // //---------------------- Event Select purchase ------------------------------\\
    // Selected_Purchase_Ref(value) {
    //   this.Get_Products_By_purchase(value);
    // },

    //  //------------------------------------ Get Products By purchase -------------------------\\

    // Get_Products_By_purchase(id) {
    //   // Start the progress bar.
    //     NProgress.start();
    //     NProgress.set(0.1);
    //   axios
    //     .get("get_Products_by_purchase/" + id)
    //      .then(response => {
    //         this.details = response.data.details;
    //         this.purchase_return = response.data.purchase_return;
    //         this.purchase_return.date = new Date().toISOString().slice(0, 10);
    //         this.Calcul_Total();
    //          NProgress.done();

    //         })
    //       .catch(error => {
    //       });
    // },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
           if (isNaN(detail.quantity) || detail.quantity < 0) {
            this.details[i].quantity = 0;
          }

          if (detail.quantity > detail.purchase_quantity) {
            this.makeToast("warning", this.$t("qty_return_is_greater_than_qty_purchased"), this.$t("Warning"));
            this.details[i].quantity = 0;
          }else if(detail.quantity > detail.stock){
            this.makeToast("warning", this.$t("qty_return_is_greater_than_Quantity_Remaining"), this.$t("Warning"));
            this.details[i].quantity = 0;
          } else {
            this.details[i].quantity = detail.quantity;
          }
          this.syncSerialsWithQty(this.details[i]);
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    //----------------------------------- increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity + 1 > detail.purchase_quantity) {
            this.makeToast("warning", this.$t("qty_return_is_greater_than_qty_purchased"), this.$t("Warning"));
          }else if(detail.quantity + 1 > detail.stock){
            this.makeToast("warning", this.$t("qty_return_is_greater_than_Quantity_Remaining"), this.$t("Warning"));
          } else {
            this.formatNumber(this.details[i].quantity++, 2);
            this.syncSerialsWithQty(this.details[i]);
          }
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //----------------------------------- decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity - 1 >= 0) {
            this.formatNumber(this.details[i].quantity--, 2);
            this.syncSerialsWithQty(this.details[i]);
          }
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    toggle_serial_number(detail, sn) {
      if (!Array.isArray(detail.serial_numbers)) {
        this.$set(detail, 'serial_numbers', []);
      }
      const idx = detail.serial_numbers.indexOf(sn);
      if (idx > -1) {
        detail.serial_numbers.splice(idx, 1);
      } else {
        if (detail.serial_numbers.length < Math.floor(detail.quantity)) {
          detail.serial_numbers.push(sn);
        } else {
          this.makeToast("warning", "Cannot select more serial numbers than product quantity", "Warning");
        }
      }
      detail.imei_number = detail.serial_numbers.join(', ');
      this.$forceUpdate();
    },

    syncSerialsWithQty(detail) {
      if (!detail.enable_serial_tracking) return;
      if (!Array.isArray(detail.serial_numbers)) {
        this.$set(detail, 'serial_numbers', []);
      }
      const targetQty = Math.floor(detail.quantity || 0);
      const available = Array.isArray(detail.available_serials) ? detail.available_serials : [];
      if (detail.serial_numbers.length < targetQty) {
        const needed = targetQty - detail.serial_numbers.length;
        const candidates = available.filter(sn => !detail.serial_numbers.includes(sn));
        detail.serial_numbers.push(...candidates.slice(0, needed));
      } else if (detail.serial_numbers.length > targetQty) {
        detail.serial_numbers.splice(targetQty);
      }
      detail.imei_number = detail.serial_numbers.join(', ');
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.total = 0;
      for (var i = 0; i < this.details.length; i++) {
        var tax = this.details[i].taxe * this.details[i].quantity;
        this.details[i].subtotal = parseFloat(
          this.details[i].quantity * this.details[i].Net_cost + tax
        );
        this.total = parseFloat(this.total + this.details[i].subtotal);
      }

      const total_without_discount = parseFloat(
        this.total - this.purchase_return.discount
      );
      this.purchase_return.TaxNet = parseFloat(
        (total_without_discount * this.purchase_return.tax_rate) / 100
      );
      this.GrandTotal = parseFloat(
        total_without_discount +
          this.purchase_return.TaxNet +
          this.purchase_return.shipping
      );

      var grand_total =  this.GrandTotal.toFixed(2);
      this.GrandTotal = parseFloat(grand_total);
    },

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.purchase_return.tax_rate)) {
        this.purchase_return.tax_rate = 0;
      } else if(this.purchase_return.tax_rate == ''){
         this.purchase_return.tax_rate = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Discount

    keyup_Discount() {
      if (isNaN(this.purchase_return.discount)) {
        this.purchase_return.discount = 0;
      } else if(this.purchase_return.discount == ''){
         this.purchase_return.discount = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Shipping

    keyup_Shipping() {
      if (isNaN(this.purchase_return.shipping)) {
        this.purchase_return.shipping = 0;
      } else if(this.purchase_return.shipping == ''){
         this.purchase_return.shipping = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    // delete_Product_Detail(id) {
    //   for (var i = 0; i < this.details.length; i++) {
    //     if (id === this.details[i].detail_id) {
    //       this.details.splice(i, 1);
    //       this.Calcul_Total();
    //     }
    //   }
    // },

    //----------------------------------- Verified Qty If Null ------------------------------\\

     verifiedForm() {
      if (this.details.length <= 0) {
        this.makeToast(
          "warning",
          this.$t("AddProductToList"),
          this.$t("Warning")
        );
        return false;
      } else {
        var count = 0;
        for (var i = 0; i < this.details.length; i++) {
          const qty = Number(this.details[i].quantity);
          if (qty > 0) {
            count += 1;
            if (this.details[i].enable_serial_tracking) {
              const snCount = (this.details[i].serial_numbers || []).length;
              if (snCount !== Math.floor(qty)) {
                this.makeToast(
                  "warning",
                  (this.details[i].name || "Product") + ": please select " + Math.floor(qty) + " serial number(s).",
                  this.$t("Warning")
                );
                return false;
              }
            }
          }
        }

        if (count === 0) {
          this.makeToast("warning", this.$t("Please_add_return_quantity"), this.$t("Warning"));
          return false;
        } else {
          return true;
        }
      }
    },

    //--------------------------------- Create Return Purchase -------------------------\\
    Create_Return_Purchase() {
      if (this.verifiedForm()) {
        this.SubmitProcessing = true;
        NProgress.start();
        NProgress.set(0.1);
        axios
          .post("returns/purchase", {
            date: this.purchase_return.date,
            supplier_id: this.purchase_return.supplier_id,
            purchase_id: this.purchase_return.purchase_id,
            warehouse_id: this.purchase_return.warehouse_id,
            statut: this.purchase_return.statut,
            notes: this.purchase_return.notes,
            tax_rate: this.purchase_return.tax_rate?this.purchase_return.tax_rate:0,
            TaxNet: this.purchase_return.TaxNet?this.purchase_return.TaxNet:0,
            discount: this.purchase_return.discount?this.purchase_return.discount:0,
            shipping: this.purchase_return.shipping?this.purchase_return.shipping:0,
            GrandTotal: this.GrandTotal,
            details: this.details
          })
          .then(response => {
            NProgress.done();
            this.makeToast(
              "success",
              this.$t("Successfully_Created"),
              this.$t("Success")
            );

            this.SubmitProcessing = false;
            this.$router.push({
              name: "index_purchase_return"
            });
          })
          .catch(error => {
            NProgress.done();
            this.SubmitProcessing = false;
            if (this.$showApiError) {
              this.$showApiError(error);
            } else if (error.response && error.response.data && error.response.data.message) {
              this.makeToast("danger", error.response.data.message, this.$t("Failed"));
            } else {
              this.makeToast("danger", (error && error.message) || this.$t("InvalidData"), this.$t("Failed"));
            }
          });
      }
    },


    //--------------------------------------- Get Elements ------------------------------\\
    GetElements() {
      let id = this.$route.params.id;
      axios
        .get(`returns/purchase/create_purchase_return/${id}`)
        .then(response => {
          this.details = response.data.details;
          this.details.forEach(d => {
            if (!Array.isArray(d.serial_numbers)) {
              this.$set(d, 'serial_numbers', []);
            }
          });
          this.purchase_return = response.data.purchase_return;
          this.purchase_return.date = new Date().toISOString().slice(0, 10);
          this.Calcul_Total();
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //----------------------------- Created function-------------------
  created() {
    this.GetElements();
  }
};
</script>
