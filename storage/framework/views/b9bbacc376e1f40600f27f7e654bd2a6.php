

<?php $__env->startSection('content'); ?>
<div class="container">
    <h1 class="text-center mb-4">Patients</h1>

    
    <form action="<?php echo e(route('patients.store')); ?>" class="mb-5" method="POST">
        <?php echo csrf_field(); ?>

        <div class="mb-3">
            <label for="nom" class="form-label">Nom :</label>
            <input type="text" class="form-control" id="nom" name="nom" placeholder="Nom...">
        </div>

        <div class="mb-3">
            <label for="prenom" class="form-label">Prénom :</label>
            <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Prénom...">
        </div>

        <div class="mb-3">
            <label for="telephone" class="form-label">Téléphone :</label>
            <input type="text" class="form-control" id="telephone" name="telephone" placeholder="Téléphone...">
        </div>

        <div class="mb-3">
            <label for="date_naissance" class="form-label">Date de naissance :</label>
            <input type="date" class="form-control" id="date_naissance" name="date_naissance">
        </div>

        <div class="mb-3">
            <label for="adresse" class="form-label">Adresse :</label>
            <input type="text" class="form-control" id="adresse" name="adresse" placeholder="Adresse...">
        </div>

        <div class="mb-3">
            <label for="allergies" class="form-label">Allergies :</label>
            <input type="text" class="form-control" id="allergies" name="allergies" placeholder="Allergies...">
        </div>

        <div class="mb-3">
            <label for="groupe_sanguin" class="form-label">Groupe sanguin :</label>
            <select class="form-control" id="groupe_sanguin" name="groupe_sanguin">
                <option value="">-- Sélectionnez --</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="antecedents" class="form-label">Antécédents :</label>
            <textarea class="form-control" id="antecedents" name="antecedents"></textarea>
        </div>

        <div class="d-grid gap-2 col-3 mx-auto">
            <button class="btn btn-primary" type="submit">Ajouter</button>
        </div>
    </form>

    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Téléphone</th>
                <th>Date de naissance</th>
                <th>Adresse</th>
                <th>Allergies</th>
                <th>Groupe sanguin</th>
                <th>Antécédents</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $patients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $patient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($patient->id); ?></td>
                <td><?php echo e($patient->nom); ?></td>
                <td><?php echo e($patient->prenom); ?></td>
                <td><?php echo e($patient->telephone); ?></td>
                <td><?php echo e($patient->date_naissance); ?></td>
                <td><?php echo e($patient->adresse); ?></td>
                <td><?php echo e($patient->allergies); ?></td>
                <td><?php echo e($patient->groupe_sanguin); ?></td>
                <td><?php echo e($patient->antecedents); ?></td>
                <td>
                    <a href="<?php echo e(route('patients.dossier', $patient->id)); ?>" class="btn btn-info btn-sm mb-1">Voir Dossier</a>
                    <a href="<?php echo e(route('patients.edit', $patient->id)); ?>" class="btn btn-warning btn-sm mb-1">Modifier</a>
                    <form action="<?php echo e(route('patients.destroy', $patient->id)); ?>" method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce patient ?')">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('DELETE'); ?>
                        <button type="submit" class="btn btn-danger btn-sm mb-1">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\medlinkProject\resources\views/patients/index.blade.php ENDPATH**/ ?>