<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;direction:rtl}
header{color:white;padding:18px 30px;text-align:center}
header h1{font-size:22px}

.wrap{
    max-width:960px;margin:28px auto;background:white;
    border-radius:14px;padding:32px;
    box-shadow:0 4px 20px rgba(0,0,0,.1);
}
h2{
    color:#1a3c5e;margin-bottom:24px;font-size:19px;
    border-bottom:3px solid #2e6da4;padding-bottom:11px;
}

/* Grid */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.fg{display:flex;flex-direction:column;gap:6px}
.fg.full{grid-column:1/-1}
label{font-size:13px;font-weight:600;color:#555}
.req{color:#dc3545;margin-left:3px}

/* Inputs */
input[type=text],input[type=date],textarea,select{
    padding:10px 13px;border:2px solid #e9ecef;
    border-radius:8px;font-size:14px;font-family:inherit;
    width:100%;transition:border .2s,box-shadow .2s;
    background:#fafafa;
}
input:focus,textarea:focus,select:focus{
    outline:none;border-color:#2e6da4;
    box-shadow:0 0 0 3px rgba(46,109,164,.15);
    background:white;
}
textarea{resize:vertical;min-height:80px;line-height:1.7}

/* ── Toggle نعم/لا ── */
.toggle-group{
    display:flex;gap:0;border-radius:8px;overflow:hidden;
    border:2px solid #e9ecef;width:fit-content;margin-top:4px;
}
.toggle-option{position:relative}
.toggle-option input[type=radio]{
    position:absolute;opacity:0;width:0;height:0;
}
.toggle-option label{
    display:flex;align-items:center;gap:6px;
    padding:9px 22px;cursor:pointer;font-size:14px;
    font-weight:600;transition:all .2s;
    background:white;color:#aaa;
    border:none;margin:0;
    user-select:none;
}
.toggle-option label:hover{background:#f8f9fa;color:#666}

/* نعم checked */
.toggle-option.opt-oui input:checked + label{
    background:#28a745;color:white;
}
/* لا checked */
.toggle-option.opt-non input:checked + label{
    background:#dc3545;color:white;
}
/* Séparateur */
.toggle-sep{
    width:1px;background:#e9ecef;flex-shrink:0;
}

/* Section headers */
.sec{
    grid-column:1/-1;
    background:linear-gradient(135deg,#e8f0fb,#dce8f7);
    padding:10px 14px;border-radius:8px;
    font-weight:700;color:#1a3c5e;
    margin-top:10px;font-size:14px;
    border-right:4px solid #2e6da4;
    display:flex;align-items:center;gap:8px;
}

/* Commission */
.commission-wrap{
    border:2px solid #e0d4c0;border-radius:10px;
    padding:14px 14px 10px;background:#fffaf4;
}
.tags-box{
    min-height:46px;border:2px solid #e9ecef;border-radius:8px;
    padding:7px 10px;background:white;
    display:flex;flex-wrap:wrap;gap:6px;align-items:center;
    cursor:text;transition:border .2s;
}
.tags-box:focus-within{
    border-color:#2e6da4;
    box-shadow:0 0 0 3px rgba(46,109,164,.12);
}
.tag{
    padding:4px 8px 4px 7px;border-radius:20px;font-size:13px;
    display:inline-flex;align-items:center;gap:4px;
    animation:pop .18s ease;
}
@keyframes pop{from{opacity:0;transform:scale(.75)}to{opacity:1;transform:scale(1)}}
.tag-predef{background:#2e6da4;color:white}
.tag-custom{background:#e67e22;color:white}
.tag-label{line-height:1}
.tag-remove{
    cursor:pointer;font-size:16px;line-height:1;
    color:rgba(255,255,255,.75);border:none;
    background:none;padding:0;transition:color .15s;
}
.tag-remove:hover{color:white}
.tag-input{
    border:none;outline:none;font-size:13px;font-family:inherit;
    min-width:130px;flex:1;background:transparent;
    direction:rtl;padding:2px 0;
}
.membres-predefs{
    display:flex;flex-wrap:wrap;gap:7px;margin-top:11px;
}
.predef-btn{
    padding:5px 14px;border-radius:20px;border:2px solid #2e6da4;
    background:white;color:#2e6da4;font-size:13px;
    font-family:inherit;cursor:pointer;transition:all .18s;user-select:none;
}
.predef-btn:hover{background:#e8f0fb}
.predef-btn.selected{background:#2e6da4;color:white}
.predef-btn.selected:hover{background:#1a3c5e;border-color:#1a3c5e}
.commission-hint{font-size:11px;color:#999;margin-top:8px}
.commission-hint kbd{
    background:#eee;border:1px solid #ccc;
    border-radius:3px;padding:1px 5px;font-size:11px;
}

/* Documents stepper dans le formulaire */
.doc-stepper{
    display:flex;align-items:stretch;gap:0;
    border-radius:12px;overflow:hidden;
    border:2px solid #e0e0e0;margin-top:10px;
}
.doc-step{flex:1;position:relative}
.doc-step-inner{
    display:flex;flex-direction:column;align-items:center;
    justify-content:center;padding:16px 8px;
    text-decoration:none;min-height:130px;
    transition:filter .2s;
}
.doc-step-inner:hover{filter:brightness(.92)}
.doc-step-divider{
    position:absolute;right:0;top:0;bottom:0;
    width:2px;background:#e0e0e0;
}
.doc-step-num{
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:bold;margin-bottom:8px;flex-shrink:0;
    border:2px solid;
}
.doc-step-icon{font-size:22px;margin-bottom:6px}
.doc-step-label{
    font-size:11px;font-weight:700;text-align:center;
    margin-bottom:8px;line-height:1.4;
}
.doc-step-status{
    font-size:10px;padding:3px 10px;border-radius:12px;font-weight:700;
}
.doc-step-print{
    font-size:10px;margin-top:5px;padding:2px 8px;border-radius:10px;
}

/* Buttons */
.btn-row{display:flex;gap:12px;margin-top:28px;justify-content:center}
.btn{
    padding:11px 28px;border:none;border-radius:8px;cursor:pointer;
    font-size:15px;font-family:inherit;text-decoration:none;
    display:inline-flex;align-items:center;gap:7px;
    font-weight:600;transition:opacity .2s,transform .1s;
}
.btn:hover{opacity:.85;transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
.btn-success  {background:#28a745;color:white}
.btn-warning  {background:#ffc107;color:#333}
.btn-secondary{background:#6c757d;color:white}

/* Erreurs */
.error-box{
    background:#f8d7da;color:#721c24;padding:13px 16px;
    border-radius:8px;margin-bottom:18px;border:1px solid #f5c6cb;
    font-size:14px;
}
.error-box ul{margin-right:18px;margin-top:5px}

/* Section docs orange pour modifier */
.sec-docs{
    background:linear-gradient(135deg,#fce8e8,#fad4d4)!important;
    color:#c0392b!important;border-right-color:#c0392b!important;
}

.io-sader{background:#fff3cd;color:#856404;border-radius:10px;padding:1px 6px;font-size:10px}
.io-wared{background:#d4edda;color:#155724;border-radius:10px;padding:1px 6px;font-size:10px}

@media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>