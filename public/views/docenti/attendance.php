<?php
$students = $students ?? [];
$attendance = $attendance ?? [];
$meeting = $meeting ?? null;
$now = new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza'));
$meetingAt = $meeting && !empty($meeting['meeting_at']) ? new DateTimeImmutable((string)$meeting['meeting_at'], new DateTimeZone('America/Fortaleza')) : $now;
?>
<div class="mb-3">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.11em;color:#6f1d2a;font-weight:700">Encontro presencial</div>
    <h1 style="font-family:Georgia,serif;color:#17313d;font-size:32px;margin:4px 0 2px">Marcar presença</h1>
    <div style="color:#716b62;font-size:13px">Presença concede XP; ausência e falta justificada não retiram XP.</div>
</div>

<section class="cq-panel">
    <div class="row g-3 mb-3">
        <div class="col-md-5"><label class="form-label fw-bold">Título do encontro</label><input id="meetingTitle" class="form-control" value="<?= htmlspecialchars((string)($meeting['title'] ?? 'Encontro da Crisma')) ?>"></div>
        <div class="col-md-4"><label class="form-label fw-bold">Data e horário</label><input id="meetingDate" type="datetime-local" class="form-control" value="<?= htmlspecialchars($meetingAt->format('Y-m-d\TH:i')) ?>"></div>
        <div class="col-md-3"><label class="form-label fw-bold">XP de presença</label><input class="form-control" value="50 XP" disabled></div>
    </div>
    <div id="attendanceMessage"></div>
    <div class="table-responsive">
        <table class="cq-teacher-table">
            <thead><tr><th>Crismando</th><th>Presente</th><th>Ausente</th><th>Justificada</th><th>Atrasado</th></tr></thead>
            <tbody>
            <?php foreach ($students as $student):
                $userId=(int)($student['userId']??0);
                $current=(string)($attendance[$userId]['status']??'ausente');
                $studentId=(int)($student['id']??0);
                $fullName=trim((string)($student['name']??'').' '.(string)($student['surname']??''));
            ?>
                <tr data-student-id="<?= $studentId ?>">
                    <td><strong><?= htmlspecialchars($fullName) ?></strong></td>
                    <?php foreach (['presente'=>'Presente','ausente'=>'Ausente','justificada'=>'Justificada','atrasado'=>'Atrasado'] as $value=>$label): ?>
                        <td><label style="cursor:pointer"><input type="radio" name="status_<?= $studentId ?>" value="<?= $value ?>" <?= $current===$value?'checked':'' ?>> <span class="d-md-none"><?= $label ?></span></label></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-sm-center mt-3">
        <small style="color:#736d64">Salvar novamente não duplica XP. Se uma marcação errada de “presente” for corrigida, o XP concedido por engano é ajustado de forma auditável.</small>
        <button id="saveAttendance" class="cq-action green" style="border:0;padding:0 22px;white-space:nowrap"><i class="fa-solid fa-check"></i> Salvar presença</button>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const btn=document.getElementById('saveAttendance');
  const box=document.getElementById('attendanceMessage');
  btn?.addEventListener('click',async()=>{
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Salvando';
    const statuses={};
    document.querySelectorAll('tr[data-student-id]').forEach(row=>{
      const id=row.dataset.studentId; const checked=row.querySelector('input[type=radio]:checked');
      if(checked) statuses[id]=checked.value;
    });
    const params=new URLSearchParams();
    params.set('mode','attendance');
    params.set('meeting_title',document.getElementById('meetingTitle').value || 'Encontro da Crisma');
    params.set('meeting_date',(document.getElementById('meetingDate').value || '').replace('T',' '));
    params.set('statuses',JSON.stringify(statuses));
    try{
      const response=await fetch('/docenti/dashboard/ricompensa-multipla',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:params.toString()});
      const data=await response.json();
      const ok=data.status==='success';
      box.innerHTML=`<div class="alert alert-${ok?'success':'danger'}">${data.message||'Operação concluída.'}</div>`;
    }catch(e){box.innerHTML='<div class="alert alert-danger">Não foi possível salvar a presença.</div>';}
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-check"></i> Salvar presença';
  });
});
</script>
