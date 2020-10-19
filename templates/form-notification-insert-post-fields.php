<div class="jet-form-editor__row" v-if="'insert_post' === currentItem.type">
	<div class="jet-form-editor__row-label"><?php
		_e( 'Enable expiration period:', 'jet-engine-post-expiration-period' );
	?></div>
    <div class="jet-form-editor__row-control">
        <input type="checkbox" v-model="currentItem.enable_expiration_period">
    </div>
</div>

<div class="jet-form-editor__row" v-if="'insert_post' === currentItem.type && currentItem.enable_expiration_period">
    <div class="jet-form-editor__row-label"><?php
        _e( 'Expiration period:', 'jet-engine-post-expiration-period' );
        ?></div>
    <div class="jet-form-editor__row-control">
        <input type="number" min="1" v-model="currentItem.expiration_period">
        <span>days</span>
    </div>
</div>


<div class="jet-form-editor__row" v-if="'insert_post' === currentItem.type && currentItem.enable_expiration_period">
    <div class="jet-form-editor__row-label"><?php
        _e( 'Expiration action:', 'jet-engine-post-expiration-period' );
        ?></div>
    <div class="jet-form-editor__row-control">

            <label for="_jet_epep__expiration_type__draft"><?php
                _e( 'Draft', 'jet-engine-post-expiration-period' );?>
            </label>
            <input type="radio" value="draft" name="expiration_type"
                   id="_jet_epep__expiration_type__draft"
                   v-model="currentItem.expiration_action"
            >

            <label for="_jet_epep__expiration_type__trash"><?php
                _e( 'Trash', 'jet-engine-post-expiration-period' );?>
            </label>
            <input type="radio" value="trash" name="expiration_type"
                   id="_jet_epep__expiration_type__trash"
                   v-model="currentItem.expiration_action"
            >

    </div>
</div>

