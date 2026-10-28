<!DOCTYPE html>
<html lang="en">

<head>

    
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T">

    
    
    
    <title>
                Bul | Transaction            </title>

    
    
    
<!-- Bootstrap -->
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Font Awesome -->
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Select2 -->
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">

<!-- AdminLTE -->
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<!-- jQuery UI -->
<link rel="stylesheet"
href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

<style>

.top-summary-bar{

    display:flex;
    align-items:center;

    gap:12px;

    flex-wrap:wrap;

    margin-left:10px;
}

.summary-link{

    color:#0066ff;

    font-weight:700;

    font-size:16px;

    white-space:nowrap;
}

.summary-link:hover{

    text-decoration:none;
    color:#0050cc;
}

.summary-divider{

    color:#888;
}

.header-ledger-select{

    margin-left:15px;
}

.select2-container .select2-selection--single{

    height:38px !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered{

    line-height:38px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow{

    height:38px !important;
}

@media(max-width:991px){

    .top-summary-bar{

        gap:8px;
    }

    .summary-link{

        font-size:14px;
    }

}

</style>
    
    
</head>

<body class="sidebar-mini-1" >

    
        <div class="wrapper">

        
                    <nav class="main-header navbar
    navbar-expand-md
    navbar-white navbar-light">

    
    <ul class="navbar-nav">
        
        <li class="nav-item">
    <a class="nav-link" data-widget="pushmenu" href="#"
                        >
        <i class="fas fa-bars"></i>
        <span class="sr-only">Toggle navigation</span>
    </a>
</li>
<li class="nav-item">
    <div class="top-summary-bar">

    <a href="item-ledger.php?id=1"
       class="summary-link">

        Gold :
        <span id="header_gold">0.000</span>

    </a>

    <span class="summary-divider">|</span>

    <a href="item-ledger.php?id=2"
       class="summary-link">

        Silver :
        <span id="header_silver">0.000</span>

    </a>

    <span class="summary-divider">|</span>

    <a href="cashbank-ledger.php?id=1"
       class="summary-link">

        Cash :
        <span id="header_cash">0.00</span>

    </a>

    <span class="summary-divider">|</span>

    <a href="bank-summary.php"
       class="summary-link">

        All Bank Total :
        <span id="header_bank_total">0.00</span>

    </a>

    <span class="summary-divider">|</span>

   <a href="party-summary.php"
   class="summary-link">

    Party Summary

</a>

<span class="summary-divider">|</span>

<a href="party-ledger.php"
   class="summary-link">

    Party Ledger

</a>

<div class="header-ledger-select">

        <select name="header_party_id"
                id="header_party_id"
                class="form-control select2"
                style="width:280px;">
        </select>

    </div>

</div>
</li>
        
        
        
            </ul>

    
    <ul class="navbar-nav ml-auto">
        
        
        
        
        
                                    <li class="nav-item dropdown user-menu">

    
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <span >
            <?php echo htmlspecialchars(current_user_name()); ?>
        </span>
    </a>

    
    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">

        
                            
        
        
        
        
        
        <li class="user-footer">
                        <a class="btn btn-default btn-flat float-right btn-block data_blank_class" href="javascript:void(0);">
                <i class="fa fa-fw fa-square"></i>
                Data Blank
            </a>
            <a class="btn btn-default btn-flat float-right  btn-block "
               href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fa fa-fw fa-power-off"></i>
                Log Out
            </a>
            <form id="logout-form" action="logout.php" method="POST" style="display: none;">
                                <input type="hidden" name="_token" value="QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T">
            </form>
        </li>

    </ul>

</li>
                    
        
            </ul>

</nav>
        
        
                    <aside class="main-sidebar sidebar-dark-primary elevation-4">

    
            <a href="index.php" class="brand-link">
<img src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/img/AdminLTELogo.png"
         alt="Bul"
         class="brand-image img-circle elevation-3"
         style="opacity:.8">

    
    <span class="brand-text font-weight-light ">
        <b>Bul</b>
    </span>

</a>
    
    
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column "
                data-widget="treeview" role="menu"
                                >
                
                <li class="nav-item has-treeview <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'menu-open' : ''; ?>">

    
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>"
   href="">

        <i class="far fa-fw fa-circle "></i>

        <p>
            Masters
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a href="items.php"
   class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>">

        <i class="far fa-fw fa-circle "></i>

        <p>
            Items

                    </p>

    </a>

</li>
<li class="nav-item">

    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'accounts.php' ? 'active' : ''; ?>"
       href="accounts.php">

        <i class="far fa-fw fa-circle"></i>

        <p>
            Accounts
        </p>

    </a>

</li>
    </ul>

</li>
<li class="nav-item">

    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'soda-entry.php' ? 'active' : ''; ?>"
       href="soda-entry.php">

        <i class="far fa-fw fa-circle"></i>

        <p>
            Soda Entry
        </p>

    </a>

</li>
<li class="nav-item has-treeview <?php echo (basename($_SERVER['PHP_SELF']) == 'transaction.php' || basename($_SERVER['PHP_SELF']) == 'transaction-list.php') ? 'menu-open' : ''; ?>">

    
    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'transaction.php' || basename($_SERVER['PHP_SELF']) == 'transaction-list.php') ? 'active' : ''; ?>"
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Transaction
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
   <ul class="nav nav-treeview">

    <li class="nav-item">

        <a class="nav-link active"
           href="transaction.php">

            <i class="far fa-fw fa-circle"></i>

            <p>Add New</p>

        </a>

    </li>

    <li class="nav-item">

        <a class="nav-link"
           href="transaction-list.php">

            <i class="far fa-fw fa-circle"></i>

            <p>List</p>

        </a>

    </li>

</ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link"
   href="bank-summary.php">

    <i class="far fa-fw fa-circle"></i>

        <p>
            Report
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="party-summary.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Party Summary

                    </p>

    </a>

</li>
<li class="nav-item">

    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bank-summary.php' ? 'active' : ''; ?>"
       href="bank-summary.php">

        <i class="far fa-fw fa-circle"></i>

        <p>
            Bank Summary
        </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="item-ledger.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Item Ledger

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="party-ledger.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Party Ledger

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="cashbank-ledger.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Cash/Bank Ledger

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="soda-report.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Soda Report

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Bank Contra
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="bank-contra.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="bank-contra-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Havaalo
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="havaalo.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="havaalo-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Rani
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="rani.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="rani-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Rani Transfer
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="rani-transfer.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="rani-transfer-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Rupu
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="rupu.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="rupu-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Rupu Transfer
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="rupu-transfer.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Add New

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="rupu-transfer-list.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            List

                    </p>

    </a>

</li>
    </ul>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="sodahistorygold.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Soda Transaction Summary Gold

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="sodahistorysilver.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Soda Transaction Summary Silver

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="tds-report.php"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            TDS Report

                    </p>

    </a>

</li>
<li  class="nav-item has-treeview ">

    
    <a class="nav-link  "
       href="" >

        <i class="far fa-fw fa-circle "></i>

        <p>
            All Shortcuts
            <i class="fas fa-angle-left right"></i>

                    </p>

    </a>

    
    <ul class="nav nav-treeview">
        <li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + m : Masters

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + s : Soda Entry

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + t : Transaction

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + p : Party Ledger

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + r : Rani

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + u : Rupu

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + h : Havaalo

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + b : Bank Contra

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + o : Soda Transaction Summary

                    </p>

    </a>

</li>
<li  class="nav-item">

    <a class="nav-link  "
       href="#"        >

        <i class="far fa-fw fa-circle "></i>

        <p>
            Alt + d : TDS Report

                    </p>

    </a>

</li>
    </ul>

</li>
            </ul>
        </nav>
        
    </div>

</aside>
