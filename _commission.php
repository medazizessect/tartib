<?php
$membresActifs = $pdo->query(
    "SELECT * FROM commission_members WHERE actif = 1 ORDER BY ordre ASC, id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$currentCommission = $commissionValue ?? '';
$selectedMembres   = [];
if (trim($currentCommission) !== '') {
    $parts = preg_split('/\s*\/\s*/u', $currentCommission);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $selectedMembres[] = $p;
    }
}
?>
<div class="commission-wrap">
    <div class="tags-box" id="tags-box" onclick="focusTagInput()">
        <div id="tags-container"></div>
        <input type="text" class="tag-input" id="tag-input"
               placeholder="اكتب الاسم واللقب وأضغط Enter..."
               autocomplete="off">
    </div>
    <div class="membres-predefs" id="membres-predefs">
        <?php foreach ($membresActifs as $m): ?>
            <?php $memberDisplay = trim(($m['titre'] ?? '') . ' - ' . ($m['nom'] ?? '')); ?>
            <button type="button" class="predef-btn"
                    data-nom="<?= htmlspecialchars($memberDisplay, ENT_QUOTES) ?>"
                    onclick="toggleMembre(this)">
                <?= htmlspecialchars($memberDisplay) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <p class="commission-hint">
        💡 انقر على الأسماء لإضافتها أو إزالتها —
        أو اكتب الاسم واللقب يدوياً واضغط <kbd>Enter</kbd>
    </p>
    <input type="hidden" name="commission" id="commission-hidden"
           value="<?= htmlspecialchars($currentCommission,ENT_QUOTES) ?>">
</div>
<script>
(function(){
    var predefNames = <?= json_encode(array_map(function($m){ return trim(($m['titre'] ?? '') . ' - ' . ($m['nom'] ?? '')); }, $membresActifs),JSON_UNESCAPED_UNICODE) ?>;
    var initial     = <?= json_encode($selectedMembres,JSON_UNESCAPED_UNICODE) ?>;
    var selected    = initial.slice();
    render(); syncBtns();
    function render(){
        var c=document.getElementById('tags-container');
        c.innerHTML='';
        selected.forEach(function(nom,i){
            var isP=predefNames.indexOf(nom)!==-1;
            var tag=document.createElement('span');
            tag.className='tag '+(isP?'tag-predef':'tag-custom');
            tag.innerHTML='<span class="tag-label">'+esc(nom)+'</span>'+
                '<button type="button" class="tag-remove" '+
                'onclick="removeTag('+i+')" title="إزالة">×</button>';
            c.appendChild(tag);
        });
        syncHidden();
    }
    function syncBtns(){
        document.querySelectorAll('.predef-btn').forEach(function(btn){
            btn.classList.toggle('selected',selected.indexOf(btn.getAttribute('data-nom'))!==-1);
        });
    }
    function syncHidden(){
        document.getElementById('commission-hidden').value=selected.join(' / ');
    }
    window.toggleMembre=function(btn){
        var nom=btn.getAttribute('data-nom');
        var idx=selected.indexOf(nom);
        if(idx===-1) selected.push(nom); else selected.splice(idx,1);
        render(); syncBtns();
    };
    window.removeTag=function(idx){
        selected.splice(idx,1); render(); syncBtns();
    };
    var inp=document.getElementById('tag-input');
    inp.addEventListener('keydown',function(e){
        if(e.key==='Enter'&&this.value.trim()){
            e.preventDefault();
            var nom=this.value.trim();
            if(selected.indexOf(nom)===-1) selected.push(nom);
            this.value=''; render(); syncBtns();
        }
        if(e.key==='Backspace'&&this.value===''&&selected.length){
            selected.pop(); render(); syncBtns();
        }
    });
    window.focusTagInput=function(){document.getElementById('tag-input').focus();};
    function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;')
                              .replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
})();
</script>
