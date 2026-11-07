const quickForm=document.getElementById("quickForm");
const trackingForm=document.getElementById("trackingForm");
const quickInput=document.getElementById("quickInput");
const trackingInput=document.getElementById("trackingInput");
const result=document.getElementById("result");
const shipmentBox=document.getElementById("shipment");
const error=document.getElementById("error");

document.getElementById("year").textContent=new Date().getFullYear();
document.getElementById("menuBtn").addEventListener("click",()=>document.getElementById("navMenu").classList.toggle("open"));
document.addEventListener("click",e=>{
  if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
  const link=e.target.closest('a[href^="#"]');
  if(!link)return;
  const target=document.querySelector(link.getAttribute("href"));
  if(!target)return;
  e.preventDefault();
  target.scrollIntoView({behavior:"smooth",block:"start"});
  document.getElementById("navMenu").classList.remove("open");
});

quickForm.addEventListener("submit",e=>{e.preventDefault();track(quickInput.value)});
trackingForm.addEventListener("submit",e=>{e.preventDefault();track(trackingInput.value)});

async function track(number){
  number=String(number||"").trim().toUpperCase();
  result.classList.remove("hidden"); shipmentBox.classList.add("hidden");
  error.classList.add("hidden");
  if(!number){error.textContent="Enter a tracking number to continue.";error.classList.remove("hidden");scrollToResult();return}
  try{
    const url=new URL(window.TRACKING_API_URL||"../public/track-api.php",window.location.href);
    url.searchParams.set("tracking",number);
    const response=await fetch(url,{headers:{Accept:"application/json"}});
    const shipment=await response.json();
    if(!response.ok){error.textContent=shipment.error||"Tracking number not found. Please check the number and try again.";error.classList.remove("hidden");scrollToResult();return}
    render(shipment);scrollToResult();
  }catch(e){error.textContent="Unable to load tracking information right now.";error.classList.remove("hidden")}
}
function render(s){
  shipmentBox.classList.remove("hidden");
  shipmentBox.classList.remove("is-visible");
  shipmentBox.classList.toggle("warehouse-route-pending",Boolean(s.warehouse_route_pending));
  document.getElementById("statusTitle").textContent=s.status;
  document.getElementById("badge").textContent=s.status;
  document.getElementById("trackingOut").textContent=s.tracking_number;
  document.getElementById("originOut").textContent=s.origin;
  document.getElementById("destinationOut").textContent=s.destination;
  document.getElementById("serviceOut").textContent=s.service;
  document.getElementById("packageOut").textContent=s.package_weight&&s.package_description?`${s.package_weight} of ${s.package_description}`:"Package details unavailable";
  const carrierDetails=document.getElementById("carrierDetails");
  if(s.carrier){document.getElementById("carrierOut").textContent=s.carrier;carrierDetails.classList.remove("hidden")}else{carrierDetails.classList.add("hidden")}

  const customerDetails=document.getElementById("customerDetails");
  if(s.customer){
    const company = s.customer.company_name ? `<span>${safe(s.customer.company_name)}</span>` : "";
    const phone = s.customer.phone ? `<span>${safe(s.customer.phone)}</span>` : "";
    customerDetails.innerHTML=`<small>CUSTOMER</small><b>${safe(s.customer.name)}</b>${company}${phone}`;
    customerDetails.classList.remove("hidden");
  }else{customerDetails.classList.add("hidden");customerDetails.innerHTML=""}

  const storageDetails=document.getElementById("storageDetails");
  if(s.warehouse){
    const charge = Number(s.warehouse.storage_charge_usd)||0;
    const daysInWarehouse = Number(s.warehouse.days_in_warehouse)||0;
    const dailyRate = Number(s.warehouse.daily_rate_per_kg_usd)||0;
    storageDetails.innerHTML=`<small>WAREHOUSE STORAGE</small><span><strong>Charge starts after:</strong> ${safe(s.warehouse.free_storage_days)} days</span><span><strong>Daily rate:</strong> $${dailyRate.toFixed(2)} USD/kg/day</span><span><strong>Current storage:</strong> $${charge.toFixed(2)} USD</span><span><strong>Formula:</strong> ${safe(s.warehouse.formula)}</span><span><strong>Days in warehouse:</strong> ${daysInWarehouse}</span>`;
    storageDetails.classList.remove("hidden");
  }else{storageDetails.classList.add("hidden");storageDetails.innerHTML=""}

  if(window.JsBarcode) window.JsBarcode("#barcode",s.tracking_number,{format:"CODE128",displayValue:true,fontSize:12,height:48,margin:0,width:1.7});
  const alertLabels={info:"INFO",attention:"ATTENTION",warning:"WARNING",critical:"CRITICAL"};
  document.getElementById("timeline").innerHTML=s.events.map((e,index)=>`
    <div class="event ${e.completed?"done":""} ${e.current?"current":""} ${e.alert?"alert":""} ${["info","attention","warning","critical"].includes(e.alert_level)?`level-${e.alert_level}`:""}" style="--event-index:${index}">
      <h4>${safe(e.title)}</h4>
      ${e.location?`<p>${safe(e.location)}</p>`:""}
      ${e.carrier_name||e.flight_number?`<small class="event-carrier"><strong>Operating flight:</strong> ${safe([e.carrier_name,e.flight_number].filter(Boolean).join(" · "))}</small>`:""}
      <time>${e.date==="Pending"?"Pending":safe(e.date)+" · "+safe(e.time)}</time>
      ${e.description?`<small class="event-note" ${e.alert?`role="status"`:""}>${e.alert_level&&e.alert_level!=="none"?`<strong>${safe(alertLabels[e.alert_level]||"ALERT")}</strong>`:""}${safe(e.description)}</small>`:``}
    </div>`).join("");
  requestAnimationFrame(()=>shipmentBox.classList.add("is-visible"));
}
function parseWeightKg(value){
  if(!value)return 0;
  const match = String(value).match(/(\d+(?:\.\d+)?)/);
  return match ? Number(match[1]) : 0;
}
function safe(v){return String(v).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]))}
function scrollToResult(){result.scrollIntoView({behavior:"smooth",block:"start"})}
