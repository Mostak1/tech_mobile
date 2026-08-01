<template>
  <div class="main-content">
    <breadcumb :page="$t('Purchased_Products_Report') || 'Purchased Products Report'" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="reports"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          placeholder: $t('Search_this_table'),
          enabled: true,
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <!-- Warehouse Filter -->
        <div slot="table-actions" class="mt-2 mb-3 quantity_alert_warehouse">
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(w => ({label: w.name, value: w.id}))"
            />
          </b-form-group>
        </div>

        <!-- Export Actions -->
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
          <b-button @click="exportPDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'actions'">
            <b-button size="sm" variant="primary" @click="showDetails(props.row)">
              <lucide-icon name="eye" class="mr-1" /> Details
            </b-button>
          </span>
          <span v-else-if="props.column.field === 'purchased_qty'">
            <b-badge variant="info">{{ props.row.purchased_qty }} {{ props.row.unit }}</b-badge>
          </span>
          <span v-else-if="props.column.field === 'sold_qty'">
            <b-badge variant="success">{{ props.row.sold_qty }} {{ props.row.unit }}</b-badge>
          </span>
          <span v-else-if="props.column.field === 'remaining_qty'">
            <b-badge :variant="props.row.remaining_qty > 0 ? 'primary' : 'danger'">
              {{ props.row.remaining_qty }} {{ props.row.unit }}
            </b-badge>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <!-- Details Modal -->
    <b-modal id="purchased-details-modal" hide-footer size="lg" :title="selectedProduct ? selectedProduct.name + ' - Variation Details' : 'Product Details'">
      <div v-if="selectedProduct">
        <div class="mb-3 p-2 bg-light rounded">
          <strong>Code:</strong> {{ selectedProduct.code }} |
          <strong>Category:</strong> {{ selectedProduct.category || 'N/A' }} |
          <strong>Total Remaining:</strong> {{ selectedProduct.remaining_qty }} {{ selectedProduct.unit }}
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="thead-dark">
              <tr>
                <th>Variation Name</th>
                <th>Purchases QTY</th>
                <th>Sell QTY</th>
                <th>Remaining QTY</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(v, idx) in selectedProduct.variations" :key="idx">
                <td><strong>{{ v.variant_name }}</strong></td>
                <td><b-badge variant="info">{{ v.purchased_qty }} {{ v.unit }}</b-badge></td>
                <td><b-badge variant="success">{{ v.sold_qty }} {{ v.unit }}</b-badge></td>
                <td>
                  <b-badge :variant="v.remaining_qty > 0 ? 'primary' : 'danger'">
                    {{ v.remaining_qty }} {{ v.unit }}
                  </b-badge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Purchased Products Report"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: 0,
      reports: [],
      warehouses: [],
      warehouse_id: "",
      selectedProduct: null
    };
  },

  computed: {
    columns() {
      return [
        {
          label: this.$t("ProductCode") || "Product Code",
          field: "code",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Name_product") || "Product Name",
          field: "name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Purchases_QTY") || "Purchases QTY",
          field: "purchased_qty",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Sell_Qty") || "Sell Qty",
          field: "sold_qty",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Remaining_QTY") || "Remaining QTY",
          field: "remaining_qty",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Action") || "Action",
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },

  methods: {
    showDetails(row) {
      this.selectedProduct = row;
      this.$bvModal.show("purchased-details-modal");
    },

    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Get_Report(1);
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Report(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Report(1);
      }
    },

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Report(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Report(this.serverParams.page);
    },

    exportPDF() {
      const self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Code", dataKey: "code" },
        { title: "Product Name", dataKey: "name" },
        { title: "Purchases QTY", dataKey: "purchased_qty" },
        { title: "Sell QTY", dataKey: "sold_qty" },
        { title: "Remaining QTY", dataKey: "remaining_qty" }
      ];
      autoTable(pdf, {
        columns: columns,
        body: self.reports,
        startY: 70,
        didDrawPage: (data) => {
          pdf.setFontSize(16);
          pdf.text("Purchased Products Report", 40, 40);
        }
      });
      pdf.save("Purchased_Products_Report.pdf");
    },

    printTableOnly() {
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }
      const reportsData = this.reports || [];
      let tableHtml = `<table class="table table-bordered"><thead><tr>
        <th>Code</th><th>Product Name</th><th>Purchases QTY</th><th>Sell QTY</th><th>Remaining QTY</th>
      </tr></thead><tbody>`;

      reportsData.forEach(row => {
        tableHtml += `<tr>
          <td>${row.code}</td>
          <td>${row.name}</td>
          <td>${row.purchased_qty} ${row.unit}</td>
          <td>${row.sold_qty} ${row.unit}</td>
          <td>${row.remaining_qty} ${row.unit}</td>
        </tr>`;
      });
      tableHtml += `</tbody></table>`;

      const w = window.open("", "_blank");
      if (!w) return;
      w.document.write(`<!doctype html><html><head><title>Purchased Products Report</title><style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
      </style></head><body><h2>Purchased Products Report</h2>${tableHtml}</body></html>`);
      w.document.close();
      w.focus();
      setTimeout(() => { w.print(); w.close(); }, 400);
    },

    Get_Report(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "report/purchased_products_report?page=" +
            page +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&warehouse_id=" +
            this.warehouse_id +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.reports = response.data.report;
          this.totalRows = response.data.totalRows;
          this.warehouses = response.data.warehouses;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  created() {
    this.Get_Report(1);
  }
};
</script>
