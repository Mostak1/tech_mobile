<template>
  <div class="main-content">
    <!-- Header with Back Button -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <breadcumb :page="$t('Supplier_Purchases_Detail') || 'Supplier Purchases Detail'" :folder="$t('Purchases') || 'Purchases'"/>
      <router-link to="/app/reports/supplier_purchases_report" class="btn btn-outline-primary btn-sm ripple">
        <lucide-icon name="arrow-left" class="mr-1" /> Back to Report
      </router-link>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else-if="supplierData" id="print-supplier-detail">
      <!-- Supplier Info Card -->
      <b-card class="mb-4 shadow-sm border-0">
        <div class="row align-items-center">
          <div class="col-md-7">
            <h4 class="text-primary font-weight-bold mb-2">{{ supplierData.supplier.name }}</h4>
            <div class="d-flex flex-wrap text-dark">
              <span class="mr-4 mb-1"><strong>Mobile:</strong> {{ supplierData.supplier.phone || 'N/A' }}</span>
              <span class="mr-4 mb-1"><strong>Address:</strong> {{ supplierData.supplier.address || 'N/A' }}</span>
              <span class="mr-4 mb-1" v-if="supplierData.supplier.email"><strong>Email:</strong> {{ supplierData.supplier.email }}</span>
              <span class="mb-1" v-if="supplierData.supplier.code"><strong>Code:</strong> {{ supplierData.supplier.code }}</span>
            </div>
          </div>
          <div class="col-md-5 text-md-right mt-3 mt-md-0">
            <div class="p-2 bg-light rounded d-inline-block text-center border">
              <span class="d-block text-muted small">Total Purchased Items</span>
              <span class="h5 font-weight-bold text-info mb-0">{{ supplierData.total_qty }} Qty</span>
            </div>
            <div class="p-2 bg-light rounded d-inline-block text-center border ml-2">
              <span class="d-block text-muted small">Total Purchased Amount</span>
              <span class="h5 font-weight-bold text-success mb-0">{{ formatCurrency(supplierData.total_amount) }}</span>
            </div>
          </div>
        </div>
      </b-card>

      <!-- Purchased Items Table Card -->
      <b-card class="wrapper shadow-sm border-0">
        <!-- Table Action Controls -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0 font-weight-bold">Purchased Product Details</h5>
          <div>
            <b-button @click="printPage()" size="sm" variant="outline-secondary" class="mr-2">
              <lucide-icon name="printer" class="mr-1" /> Print
            </b-button>
            <b-button @click="exportPDF()" size="sm" variant="outline-success">
              <lucide-icon name="copy" class="mr-1" /> Export PDF
            </b-button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped border">
            <thead class="bg-primary text-white">
              <tr>
                <th class="py-3">Product Name</th>
                <th class="text-center py-3">Purchases Qty</th>
                <th class="text-right py-3">Purchases Amount</th>
                <th class="text-center py-3">Purchases Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in supplierData.details" :key="item.id">
                <td class="font-weight-bold text-dark py-3">
                  {{ item.product_name }}
                  <small class="d-block text-muted" v-if="item.ref">Ref: {{ item.ref }}</small>
                </td>
                <td class="text-center py-3">
                  <b-badge variant="info" class="px-3 py-2 font-weight-bold">{{ item.purchases_qty }}</b-badge>
                </td>
                <td class="text-right py-3 font-weight-bold text-success">
                  {{ formatCurrency(item.purchases_amount) }}
                </td>
                <td class="text-center py-3 text-muted">
                  {{ item.purchases_date }}
                </td>
              </tr>
              <tr v-if="!supplierData.details || !supplierData.details.length">
                <td colspan="4" class="text-center text-muted py-4">
                  No purchased products found for this supplier.
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-light font-weight-bold" v-if="supplierData.details && supplierData.details.length">
              <tr>
                <td class="py-3">Total</td>
                <td class="text-center py-3 text-info font-weight-bold h6 mb-0">{{ supplierData.total_qty }}</td>
                <td class="text-right py-3 text-success font-weight-bold h6 mb-0">{{ formatCurrency(supplierData.total_amount) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </b-card>
    </div>
  </div>
</template>

<script>
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Supplier Purchases Detail"
  },
  data() {
    return {
      isLoading: true,
      supplierData: null,
    };
  },
  methods: {
    formatCurrency(val) {
      const num = Number(val) || 0;
      return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    getSupplierDetails() {
      this.isLoading = true;
      const id = this.$route.params.id;
      axios
        .get("report/supplier_purchases_detail/" + id)
        .then(response => {
          this.supplierData = response.data;
          this.isLoading = false;
        })
        .catch(() => {
          this.isLoading = false;
        });
    },

    printPage() {
      const printArea = document.getElementById("print-supplier-detail");
      if (!printArea) {
        window.print();
        return;
      }
      const win = window.open("", "", "height=700,width=900");
      win.document.write("<html><head><title>Supplier Purchases Details</title>");
      win.document.write("<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>");
      win.document.write("<style>body{padding:20px; font-family:sans-serif;} .btn, button, a.btn{display:none !important;}</style>");
      win.document.write("</head><body>");
      win.document.write(printArea.innerHTML);
      win.document.write("</body></html>");
      win.document.close();
      win.focus();
      setTimeout(() => {
        win.print();
        win.close();
      }, 500);
    },

    exportPDF() {
      if (!this.supplierData) return;
      const pdf = new jsPDF("p", "pt", "a4");
      pdf.setFontSize(16);
      pdf.text("Supplier Purchases Details - " + (this.supplierData.supplier.name || ""), 40, 40);

      pdf.setFontSize(10);
      pdf.text("Mobile: " + (this.supplierData.supplier.phone || "N/A"), 40, 60);
      pdf.text("Address: " + (this.supplierData.supplier.address || "N/A"), 40, 75);

      const tableData = (this.supplierData.details || []).map(item => [
        item.product_name,
        String(item.purchases_qty),
        this.formatCurrency(item.purchases_amount),
        item.purchases_date
      ]);

      autoTable(pdf, {
        head: [["Product Name", "Purchases Qty", "Purchases Amount", "Purchases Date"]],
        body: tableData,
        startY: 95,
        theme: "grid",
        headStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold",
        },
      });

      pdf.save("Supplier_Purchases_" + (this.supplierData.supplier.name || "Details") + ".pdf");
    }
  },
  created() {
    this.getSupplierDetails();
  }
};
</script>
