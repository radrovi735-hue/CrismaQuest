<?php
$schoolYears = $schoolYears ?? [];
$availableIcons = $availableIcons ?? [];
?>
<div class="modal fade" id="teacherClassCreateModal" tabindex="-1" role="dialog" aria-labelledby="teacherClassCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="/docenti/classi">
                <div class="modal-header">
                    <h5 class="modal-title" id="teacherClassCreateModalLabel">Criar nova turma</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="class_name">Nome da turma</label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required placeholder="Ex.: Crisma 2026–2027">
                    </div>
                    <div class="form-group mt-3">
                        <label for="school_year_id">Ano da Crisma</label>
                        <select class="form-control" id="school_year_id" name="school_year_id" required>
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?= (int) $year['id_anno'] ?>"><?= htmlspecialchars((string) $year['anno_scolastico']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label for="class_icon">Símbolo da turma</label>
                        <select class="form-control" id="class_icon" name="class_icon">
                            <?php foreach ($availableIcons as $icon): ?>
                                <option value="<?= htmlspecialchars($icon) ?>"><?= htmlspecialchars(str_replace(['fa-','-'],['',' '],$icon)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label for="class_color">Cor de destaque</label>
                        <input type="color" class="form-control" id="class_color" name="class_color" value="#6f1d2a">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar turma</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherClassEditModal" tabindex="-1" role="dialog" aria-labelledby="teacherClassEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="/docenti/classi/0/modifica" id="teacherClassEditForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="teacherClassEditModalLabel">Editar turma</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_class_name">Nome da turma</label>
                        <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="edit_class_icon">Símbolo da turma</label>
                        <select class="form-control" id="edit_class_icon" name="class_icon">
                            <?php foreach ($availableIcons as $icon): ?>
                                <option value="<?= htmlspecialchars($icon) ?>"><?= htmlspecialchars(str_replace(['fa-','-'],['',' '],$icon)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label for="edit_class_color">Cor de destaque</label>
                        <input type="color" class="form-control" id="edit_class_color" name="class_color" value="#6f1d2a">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
