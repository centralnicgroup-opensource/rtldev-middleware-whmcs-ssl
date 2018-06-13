DELETE THIS FILE LATER


<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    Select Product Group
    <select>
        <?php
        foreach ($product_groups as $value) {
            ?>
            <option><?php echo $value['name'];?></option>
            <?php
        }?>
    </select>
    <br><br>
    Add Profit Margin
    <input type=text name="" value=""></input>
    <br><br>
    <input type="submit" class="btn btn-primary" name="import-products" value="Import Products"/>
</form>





##########################
<?php



    $newid = insert_query("tblproducts",array("type" => "other",
            "gid" => $productgroupid,
            "name" => $ssl_certificate,
            "paytype" => "onetime",
        ));
        // echo $newid;
    //tblpricing table
    $stmt = $pdo->prepare("SELECT * FROM tblpricing WHERE type='product' AND relid=? ORDER BY id DESC LIMIT 1");
    $stmt->execute(array($newid));
    $tblpricing = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!empty($profit_margin)){
        $price = $price + $profit_margin; //TODO - testing - for now i do addition only
    }
    if(!empty($tblpricing)){
        //update other columns as well (for example - from insert comannd below) TODO - dont need IMO
        $update_stmt=$pdo->prepare("UPDATE tblpricing SET msetupfee='0', monthly=? WHERE id=?");
        $update_stmt->execute(array($tblpricing["id"], $price));
    }else{
        $insert_stmt = $pdo->prepare("INSERT INTO tblpricing (type, currency, relid, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, monthly, quarterly, semiannually, annually, biennially,triennially) VALUES ('product', '1', ?, '0', '-1', '-1', '-1', '-1', '?', '-1', '-1', '-1', '-1', '-1')");
        $insert_stmt->execute(array($newid, $price));
    }
}
}// echo "<pre>"; print_r($data); echo "</pre>";



#################################################################
<form action="addonmodules.php?module=ispapissl_addon" method="POST">
    {*  the following is backup*}
    {* {if empty($selected_product_group)}
      <div class='errorbox'><strong><span class='title'>ERROR!</span></strong><br>Please select a product group</div><br>
    {/if} *}

    {* {if isset($smarty.post.importproducts) && !empty($selected_product_group)}
      <div class='infobox'><strong><span class='title'>Update successful!</span></strong><br>Your SSL products has been updated successfully.</div><br>
    {/if}

    Select Product Group
    <select name="selectedproductgroup">
        <option></option>
        {foreach $product_groups as $value}
            <option>{$value['name']}</option>
        {/foreach}
    </select>
    <br><br>
    Add Profit Margin
    <input type=text name="profitmargin" value=""></input>
    <br><br>
    <input type="submit" class="btn btn-primary" name="importproducts" value="Import Products"/>
</form> *}
